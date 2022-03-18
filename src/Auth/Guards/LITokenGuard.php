<?php

namespace LiveIntent\LaravelCommon\Auth\Guards;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Illuminate\Contracts\Auth\UserProvider;
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
        private UserProvider $userProvider,
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
        // There's something funky going on where the request object is missing
        // headers in some cases, possibly due to the use of multiple guards
        // so we grab it directly from php and then proceed as per usual
        $request->headers->set(
            'authorization',
            Arr::get(getallheaders(), 'Authorization')
                ?: Arr::get(getallheaders(), 'authorization')
                ?: ''
        );

        $liToken = $request->bearerToken();

        // Verify token
        try {
            $signer = Sha256::create();
            $publicKey = InMemory::plainText(config('liveintent.auth.li_token.public_key'));

            $configuration = Configuration::forSymmetricSigner($signer, $publicKey);

            /** @var Plain $token */
            $token = $configuration->parser()->parse($liToken);

            $configuration->validator()->assert(
                $token,
                new StrictValidAt(SystemClock::fromUTC()),
                new SignedWith($signer, $publicKey),
            );
        } catch (Throwable $_e) {
            // Token was invalid
            return null;
        }

        if ($userId = $token->claims()->get('sub')) {
            return $this->userProvider->retrieveById($userId);
        }
        // User ID on 'sub' not found
        return null;
    }
}
