<?php

namespace LiveIntent\LaravelCommon\Auth;

use Throwable;
use Illuminate\Http\Request;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Illuminate\Contracts\Auth\Authenticatable;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

class LITokenGuard
{
    /**
     * Create a new internal token guard instance.
     * @throws Throwable
     */
    public function __construct(
        private LITokenUserProvider $userProvider,
    ) {
    }

    /**
     * Get the user for the incoming request.
     *
     * @param Request $request
     * @return Authenticatable|null
     */
    public function user(Request $request): ?Authenticatable
    {
        try {
            $signer = Sha256::create();
            $publicKey = InMemory::plainText(config('liveintent.auth.li_token.public_key'));
            $configuration = Configuration::forSymmetricSigner($signer, $publicKey);

            // After we've set up the configuration, we need to attempt to
            // parse the raw JWT into a workable object for validation.
            $liToken = $configuration->parser()->parse(
                $request->bearerToken()
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
            return $this->userProvider->retrieveByLIToken($liToken);
        } catch (Throwable $e) {
            Log::debug('ErrorValidatingLIToken', [ 'message' => $e->getMessage() ]);

            return null;
        }
    }
}
