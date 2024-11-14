<?php

namespace LiveIntent\LaravelCommon\Auth;

use Throwable;
use Illuminate\Http\Request;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Validated;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Auth\Authenticatable;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

/**
 * Updated from discussions found here: https://laracasts.com/discuss/channels/laravel/login-event
 */
class LITokenGuard
{
    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected Dispatcher $events;
    protected \Symfony\Component\HttpFoundation\Request|Request $request;

    /**
     * Create a new internal token guard instance.
     * @throws Throwable
     */
    public function __construct(
        private String $name,
        private LITokenUserProvider $userProvider,
    ) {
    }

    /**
     * Get the user for the incoming request.
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        $this->fireAttemptEvent(['token' => $this->getBearerToken()]);
        if (!$bearerToken = $this->getBearerToken()) {
            Log::debug('NoBearerTokenPresent');
            $this->fireFailedEvent(null, ['token' => $bearerToken]);

            return null;
        }

        $maybeUser = null;

        try {
            $signer = Sha256::create();
            $publicKey = InMemory::plainText(config('liveintent.auth.li_token.public_key'));
            $configuration = Configuration::forSymmetricSigner($signer, $publicKey);

            // After we've set up the configuration, we need to attempt to
            // parse the raw JWT into a workable object for validation.
            $liToken = $configuration->parser()->parse(
                $bearerToken
            );

            // We'll make some basic assertions about the token, to ensure
            // that it is not expired and comes from a source we trust.
            $configuration->validator()->assert(
                $liToken,
                new StrictValidAt(SystemClock::fromUTC()),
                new SignedWith($signer, $publicKey),
            );

            // Finally, we'll use the configured user provider to retrieve
            // an authenticatable instance so the app can work with it.
            $maybeUser = $this->userProvider->retrieveByLIToken($liToken);
            if ($maybeUser) {
                $this->fireValidatedEvent($maybeUser);
                $this->fireAuthenticatedEvent($maybeUser);
                $this->fireLoginEvent($maybeUser);
            } else {
                $this->fireFailedEvent($maybeUser, ['token' => $bearerToken]);
            }

            return $maybeUser;

        } catch (Throwable $e) {
            Log::debug('ErrorValidatingLIToken: ' . $e->getMessage());
            $this->fireFailedEvent($maybeUser, ['token' => $bearerToken]);
        }

        return null;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function setDispatcher(Dispatcher $events): void
    {
        $this->events = $events;
    }

    /**
     * Get the current request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request|null
     */
    public function getRequest()
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (!isset($this->request)) {
            $this->request = Request::createFromGlobals();
        }

        return $this->request;
    }

    /**
     * Set the current request instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Request|Request|null  $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    protected function getBearerToken(): null|string
    {
        $request = $this->getRequest();
        if ($request instanceof Request) {
            return $request->bearerToken();
        }

        $authorizationHeader = $request->headers->get('Authorization') ?? '';

        return trim(str_ireplace('Bearer', '', $authorizationHeader));
    }

    /**
     * Fire the attempt event with the arguments.
     *
     * @param  array  $credentials
     * @param  bool  $remember
     * @return void
     */
    protected function fireAttemptEvent(array $credentials, $remember = false)
    {
        $this->events->dispatch(new Attempting($this->name, $credentials, $remember));
    }

    /**
     * Fires the validated event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    protected function fireValidatedEvent($user)
    {
        $this->events->dispatch(new Validated($this->name, $user));
    }

    /**
     * Fire the login event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  bool  $remember
     * @return void
     */
    protected function fireLoginEvent($user, $remember = false)
    {
        $this->events->dispatch(new Login($this->name, $user, $remember));
    }

    /**
     * Fire the authenticated event if the dispatcher is set.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    protected function fireAuthenticatedEvent($user)
    {
        $this->events->dispatch(new Authenticated($this->name, $user));
    }

    /**
     * Fire the failed authentication attempt event with the given arguments.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  array  $credentials
     * @return void
     */
    protected function fireFailedEvent($user, array $credentials)
    {
        $this->events->dispatch(new Failed($this->name, $user, $credentials));
    }
}
