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
     * Configuration container for the JWT Builder and Parser.
     */
    private Configuration $configuration;

    /**
     * Create a new internal token guard instance.
     */
    public function __construct(
        private UserProvider $userProvider
    ) {
        $publicKey = InMemory::plainText(config('auth.li_token.keys.public'));

        $ecdsa = Sha256::create();
        $this->configuration = Configuration::forSymmetricSigner(
            $ecdsa,
            $publicKey
        );

        $this->configuration->setValidationConstraints(
            new SignedWith($ecdsa, $publicKey),
        );
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

        try {
            /** @var Plain $token */
            $token = $this->configuration->parser()->parse($liToken);

            $this->configuration->validator()->assert(
                $token,
                new StrictValidAt(SystemClock::fromUTC()),
                ...$this->configuration->validationConstraints(),
            );
        } catch (Throwable $_e) {
            // Token was not successfully authenticated
            return null;
        }

        if ($userId = $token->claims()->get('sub')) {
            return $this->userProvider->retrieveById($userId);
        }

        // User not found
        return null;
    }
}
