<?php

namespace LiveIntent\LaravelCommon\Auth;

use Lcobucci\JWT\Token\Plain;

interface LITokenUserProvider
{
    /**
     * Retrieve a user via the LI Token.
     *
     * @param  Plain  $liToken
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByLIToken(Plain $liToken);
}
