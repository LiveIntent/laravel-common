<?php

namespace LiveIntent\LaravelCommon\Auth\Guards;

use Lcobucci\JWT\Builder;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

class LITokenGuard
{
    /**
     * Configuration container for the JWT Builder and Parser.
     */
    private Configuration $configuration;

    /**
     * String representation of the issuer of the tokens.
     */
    private string $iss;

    /**
     * Intended audience for the generated tokens.
     */
    private string $aud;

    /**
     * Create a new internal token guard instance.
     */
    public function __construct(
        private UserProvider $userProvider
    ) {
        $this->iss = config('auth.li_token.config.issued_by');
        $this->aud = config('auth.li_token.config.permitted_for');

        $publicKey = InMemory::plainText(config('auth.li_token.keys.public'));

        $ecdsa = Sha256::create();
        $this->configuration = Configuration::forSymmetricSigner(
            $ecdsa,
            $publicKey
        );

        $this->configuration->setValidationConstraints(
            new IssuedBy($this->iss),
            new PermittedFor($this->aud),
            new SignedWith($ecdsa, $publicKey)
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

        $token = $request->bearerToken();

        if (! $this->isActive($token)) {
            return null;
        }

        if ($userId = $this->getClaims($token)->get('sub')) {
            return $this->userProvider->retrieveById($userId);
        }

        return null;
    }

    /**
     * Issue a new signed token to be used.
     *
     * @param Builder $builder
     * @return Plain
     */
    public function issue(Builder $builder): Plain
    {
        return $builder->getToken(
            $this->configuration->signer(),
            $this->configuration->signingKey()
        );
    }

    /**
     * Attempt to parse a jwt.
     *
     * @param string|null $jwt
     * @return Plain|null
     */
    public function parse(?string $jwt): ?Plain
    {
        if (! $jwt) {
            return null;
        }

        return rescue(
            fn () => $this->configuration->parser()->parse($jwt)
        );
    }

    /**
     * Check if a token should be considered active.
     *
     * @param string|null $jwt
     * @return bool
     */
    public function isActive(?string $jwt): bool
    {
        if (! $token = $this->parse($jwt)) {
            return false;
        }

        return $this->verify($token);
    }

    /**
     * Verify that a token passes all required constraints.
     *
     * @param Plain $token
     * @return bool
     */
    public function verify(Plain $token): bool
    {
        return rescue(function () use ($token) {
            $this->configuration->validator()->assert(
                $token,
                new StrictValidAt(SystemClock::fromUTC()),
                ...$this->configuration->validationConstraints(),
            );

            return true;
        });
    }

    /**
     * Get the claims of a jwt.
     *
     * @param string|null $jwt
     * @return DataSet
     */
    public function getClaims(?string $jwt): DataSet
    {
        return $this->parse($jwt)?->claims() ?: new DataSet([], '');
    }
}
