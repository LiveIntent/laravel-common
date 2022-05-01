<?php

namespace LiveIntent\LaravelCommon\Auth;

use Lcobucci\JWT\Token\Plain;

trait CreatesAuthenticatablesFromLIToken
{
    /**
     * Retrieve a user via the LI Token.
     *
     * @param  Plain  $liToken
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    abstract public function retrieveByLIToken(Plain $liToken);

    /**
     * Get a transient user object from the LI Token.
     */
    protected function getAuthenticatableFromToken(Plain $liToken)
    {
        $claims = $liToken->claims();

        return $this->createModel()->forceFill([
            'id' => (int) $claims->get('sub'),
            'name' => $claims->get('name'),
            'email' => $claims->get('email'),
            'hash_id' => $claims->get('li')['hash_id'],
            'permission_type' => $claims->get('li')['permission_type'],
        ]);
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class();
    }
}
