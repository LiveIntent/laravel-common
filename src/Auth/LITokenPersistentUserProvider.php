<?php

namespace LiveIntent\LaravelCommon\Auth;

use Lcobucci\JWT\Token\Plain;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Auth\Authenticatable;

class LITokenPersistentUserProvider implements LITokenUserProvider
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
        if (! $authenticatable = $this->getAuthenticatableFromToken($liToken)) {
            return;
        };

        $this->persist($authenticatable);

        return $this->retrieveById($authenticatable->getAuthIdentifier());
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $model = $this->createModel();

        return $model->newQuery()
                    ->where($model->getAuthIdentifierName(), $identifier)
                    ->first();
    }

    /**
     * Ensure the user is persisted in the database.
     *
     * @param  Authenticatable  $user
     */
    public function persist(Authenticatable $user)
    {
        $model = $this->createModel();

        Log::debug('PersistingUser', ['user_id' => $user->getAuthIdentifier()]);

        // If the user has defined an override method for how the
        // authenticatable should be persisted, we'll use that
        // to enable easy customization for each use case.
        if (method_exists($user, 'persistFromTransient')) {
            return $user->persistFromTransient();
        }

        // By default, we'll persist the user info using just the
        // id of the user, so the app has a record of the user
        // but we will not persist additional attributes.
        $key = $model->getAuthIdentifierName();
        $model::upsert(
            [[$key => $user->getAuthIdentifier()]],
            [$key],
            [],
        );
    }
}
