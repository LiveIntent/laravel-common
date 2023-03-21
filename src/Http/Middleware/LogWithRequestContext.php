<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Parser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Lcobucci\JWT\Encoding\JoseEncoder;
use LiveIntent\LaravelCommon\Auth\User;
use LiveIntent\LaravelCommon\Log\LogScrubber;
use Symfony\Component\HttpFoundation\Response;

class LogWithRequestContext
{
    private array $ignorePaths;
    private int $messageMaxSizeBytes;
    private bool $shouldLogSessionInfo;

    private ?int $maybeUserId;
    private ?int $maybeActorId;

    public function __construct()
    {
        $this->ignorePaths = config('liveintent.logging.ignore_paths', []);
        $this->messageMaxSizeBytes = config('liveintent.logging.message_max_size_bytes', 13000);
        $this->shouldLogSessionInfo = config('liveintent.logging.log_session_info', false);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $requestId = $request->header('x-request-id') ?: str()->uuid();
        $maybeUnverifiedToken = $this->getUnverifiedToken($request);
        $this->maybeUserId = $this->getUserId($maybeUnverifiedToken);
        $this->maybeActorId = $this->getActorId($maybeUnverifiedToken) ?? $this->maybeUserId;

        Log::withContext([
            'request_id' => $requestId,
            'request_method' => $request->method(),
            'request_path' => "/" . $request->path(),
            'user' => [
                'id' => $this->maybeUserId,
                'actor' => $this->maybeActorId,
            ],
            'controller_action' => optional($request->route())->getActionName(),
        ]);

        // Only log if the path should not be ignored
        if (! $request->is($this->ignorePaths)) {
            $this->logIncomingRequest($request);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('x-request-id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        // Only log if the path should not be ignored
        if ($request->is($this->ignorePaths)) {
            return;
        }

        $maybeUnverifiedToken = $this->getUnverifiedToken($request);
        $this->maybeUserId = $this->getUserId($maybeUnverifiedToken);
        $this->maybeActorId = $this->getActorId($maybeUnverifiedToken) ?? $this->maybeUserId;

        $this->logOutgoingResponse($request, $response);
    }

    protected function logIncomingRequest(Request $request): void
    {
        $method = $request->getMethod();
        $fullUri = $request->getRequestUri();
        $userDisplay = $this->getUserDisplay();

        Log::info("Begin processing request $method $fullUri for $userDisplay");
        Log::debug("Incoming Request Headers: " . json_encode($this->getSanitizedHeaders($request)));
        $this->logMultiPart("Incoming Request Body", json_encode($this->getSanitizedPayload($request)));
        if ($this->shouldLogSessionInfo) {
            Log::debug("Incoming Session Info: " . json_encode($this->getSanitizedSessionVariables($request)));
        }
    }

    protected function logOutgoingResponse(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $fullUri = $request->getRequestUri();
        $statusCode = $response->getStatusCode();

        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');
        $timeElapsedMs = $startTime ? floor((microtime(true) - $startTime) * 1000) : null;

        Log::debug("Outgoing Response Headers: " . json_encode($this->getSanitizedHeaders($response)));
        $this->logMultiPart("Outgoing Response Body", json_encode($this->getSanitizedPayload($response)));
        if ($this->shouldLogSessionInfo) {
            Log::debug("Outgoing Session Info: " . json_encode($this->getSanitizedSessionVariables($response)));
        }

        $userDisplay = $this->getUserDisplay();
        Log::info("Completed processing request $statusCode $method $fullUri for $userDisplay in $timeElapsedMs ms");
    }

    protected function getUnverifiedToken(Request $request): ?Plain
    {
        if ($bearerToken = $request->bearerToken()) {
            $parser = new Parser(new JoseEncoder());
            /** @var Plain $unverifiedToken */
            $unverifiedToken = $parser->parse($bearerToken);

            return $unverifiedToken;
        }

        return null;
    }

    protected function getUserId(Plain $unverifiedToken = null): ?int
    {
        try {
            if (Auth::check()) {
                return (int)Auth::id();
            }
        } catch (Exception $exception) {
            // ignore and continue
        }

        if ($unverifiedToken !== null) {
            return (int) $unverifiedToken->claims()->get('sub');
        }

        return null;
    }

    protected function getActorId(Plain $unverifiedToken = null): ?int
    {
        try {
            if (Auth::check()) {
                /* @var User $user */
                $user = Auth::user();

                /**
                 * @psalm-suppress UndefinedInterfaceMethod
                 */
                return $user->getActorIdentifier();
            }
        } catch (Exception $exception) {
            // ignore and continue
        }

        if ($unverifiedToken !== null) {
            $claims = $unverifiedToken->claims();

            return $claims->has('act') ? $claims->get('act')['sub'] : null;
        }

        return null;
    }

    /**
     * Extract the session ID from the given request.
     */
    protected function getSessionId(Request $request): ?string
    {
        return $request->hasSession()
            ? $request->session()->getId()
            : null;
    }

    /**
     * Extract the session variables from the given request.
     */
    protected function getSanitizedSessionVariables(Request|Response $r): array
    {
        if ($r instanceof Request) {
            $request = $r;

            return $request->hasSession()
                ? LogScrubber::singleton()->hideSensitiveValues($request->session()->all())
                : [];
        }

        $response = $r;

        return method_exists($response, 'hasSession') && $response->hasSession()
            ? LogScrubber::singleton()->hideSensitiveValues($response->session()->all())
            : [];
    }

    /**
     * Format the given headers.
     */
    protected function getSanitizedHeaders(Request|Response $r): array
    {
        $headers = collect($r->headers->all())
            ->map(function ($header) {
                return $header[0];
            })->toArray();

        return LogScrubber::singleton()->hideSensitiveValues($headers);
    }

    /**
     * Format the given payload.
     */
    protected function getSanitizedPayload(Request|Response $r): array
    {
        $payload = [];

        if ($r instanceof Request) {
            $request = $r;
            $files = $request->files->all();

            array_walk_recursive($files, function (&$file) {
                $file = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->isFile() ? ($file->getSize() / 1000) . 'KB' : '0',
                ];
            });

            $payload = array_replace_recursive($request->input(), $files);
        } else {
            $response = $r;
            if ($response->headers->get('Content-Type') === 'application/json') {
                $payload = json_decode($response->getContent(), true);
            }
        }

        return LogScrubber::singleton()->hideSensitiveValues($payload);
    }

    protected function logMultiPart(string $messageContext, string $message): void
    {
        $payloadMessages = $this->toMultiPartJsonString($message);

        if (count($payloadMessages) === 1) {
            Log::debug("$messageContext: " . $payloadMessages[0]);
        } else {
            $size = count($payloadMessages);
            for ($i = 0; $i < $size; $i++) {
                Log::debug("$messageContext [" . ($i + 1) . " / $size]: " . $payloadMessages[$i]);
            }
        }
    }

    /**
     * @param string $message
     * @return array<string>
     */
    protected function toMultiPartJsonString(string $message): array
    {
        $splitMessages = [];

        $index = 0;
        while (strlen($message) > (($index + 1) * $this->messageMaxSizeBytes)) {
            $splitMessages[] = substr($message, ($index * $this->messageMaxSizeBytes), $this->messageMaxSizeBytes);
            $index++;
        }

        $splitMessages[] = substr($message, ($index * $this->messageMaxSizeBytes), $this->messageMaxSizeBytes);

        return $splitMessages;
    }

    protected function getUserDisplay(): string
    {
        if ($this->maybeUserId == null) {
            return "unauthenticated user";
        }

        $display = "User " . $this->maybeUserId;
        if ($this->maybeActorId !== null && $this->maybeActorId !== $this->maybeUserId) {
            $display .= " (Actor: " . $this->maybeActorId . ")";
        }

        return $display;
    }
}
