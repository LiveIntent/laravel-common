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
use LiveIntent\LaravelCommon\Util\StringUtil;
use Symfony\Component\HttpFoundation\Response;

class LogWithRequestContext
{
    private array $ignorePaths;
    private int $messageMaxSizeBytes;
    private bool $shouldLogSessionInfo;
    private bool $shouldLogTokenInfo;
    private bool $shouldLogRequestHeaders;
    private bool $shouldLogRequestPayload;
    private bool $shouldLogResponseHeaders;
    private bool $shouldLogResponsePayload;

    private ?Plain $maybeUnverifiedToken;
    private ?int $maybeUserId;
    private ?int $maybeActorId;

    public function __construct()
    {
        $this->ignorePaths = config('liveintent.logging.ignore_paths', []);
        $this->messageMaxSizeBytes = config('liveintent.logging.message_max_size_bytes', 5500);
        $this->shouldLogSessionInfo = config('liveintent.logging.log_session_info', false);
        $this->shouldLogTokenInfo = config('liveintent.logging.log_token_info', true);
        $this->shouldLogRequestHeaders = config('liveintent.logging.log_request_headers', true);
        $this->shouldLogRequestPayload = config('liveintent.logging.log_request_payload', true);
        $this->shouldLogResponseHeaders = config('liveintent.logging.log_response_headers', true);
        $this->shouldLogResponsePayload = config('liveintent.logging.log_response_payload', true);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $requestId = $request->header('x-request-id') ?: str()->uuid();
        $this->maybeUnverifiedToken = $this->getUnverifiedToken($request);
        $this->maybeUserId = $this->getUserId($this->maybeUnverifiedToken);
        $this->maybeActorId = $this->getActorId($this->maybeUnverifiedToken) ?? $this->maybeUserId;

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
        if ($this->maybeUnverifiedToken !== null && $this->shouldLogTokenInfo) {
            Log::debug("Incoming User Token", ['token' => $this->maybeUnverifiedToken->claims()->all()]);
        }
        if ($this->shouldLogRequestHeaders) {
            Log::debug("Incoming Request Headers: " . json_encode($this->getSanitizedHeaders($request)));
        }
        if ($this->shouldLogRequestPayload) {
            $this->logMultiPart("Incoming Request Payload", json_encode($this->getSanitizedPayload($request)));
        }
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

        if ($this->shouldLogResponseHeaders) {
            Log::debug("Outgoing Response Headers: " . json_encode($this->getSanitizedHeaders($response)));
        }
        if ($this->shouldLogResponsePayload) {
            $this->logMultiPart("Outgoing Response Body", json_encode($this->getSanitizedPayload($response)));
        }
        if ($this->shouldLogSessionInfo) {
            Log::debug("Outgoing Session Info: " . json_encode($this->getSanitizedSessionVariables($response)));
        }

        $userDisplay = $this->getUserDisplay();
        Log::info("Completed processing request $statusCode $method $fullUri for $userDisplay in $timeElapsedMs ms");
    }

    protected function getUnverifiedToken(Request $request): ?Plain
    {
        if ($bearerToken = $request->bearerToken()) {
            try {
                $parser = new Parser(new JoseEncoder());
                /** @var Plain $unverifiedToken */
                $unverifiedToken = $parser->parse($bearerToken);

                return $unverifiedToken;
            } catch (Exception $exception) {
                // token was provided, but not parseable, ignore and continue
            }
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
        $payloadMessages = StringUtil::toMultipart($message, $this->messageMaxSizeBytes);

        if (count($payloadMessages) === 1) {
            Log::debug("$messageContext: " . $payloadMessages[0]);
        } else {
            $size = count($payloadMessages);
            for ($i = 0; $i < $size; $i++) {
                Log::debug("$messageContext [" . ($i + 1) . " / $size]: " . $payloadMessages[$i]);
            }
        }
    }

    protected function getUserDisplay(): string
    {
        if (! isset($this->maybeUserId)) {
            return "unknown user";
        }

        $display = "User " . $this->maybeUserId;
        if ($this->maybeActorId !== null && $this->maybeActorId !== $this->maybeUserId) {
            $display .= " (Actor: " . $this->maybeActorId . ")";
        }

        return $display;
    }
}
