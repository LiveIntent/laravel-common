<?php

namespace LiveIntent\LaravelCommon\Auth;

use Lcobucci\JWT\Token\Plain;

class LITokenTransientUserProvider implements LITokenUserProvider
{
    use CreatesAuthenticatablesFromLIToken;

    /**
     * Create a new persistent user provider..
     */
    public function __construct(protected $model)
    {
    }

    /**
     * Retrieve a user via the LI Token.
     *
     * @param  Plain  $liToken
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByLIToken(Plain $liToken)
    {
        return $this->getAuthenticatableFromToken($liToken);
    }
}
