<?php

namespace LiveIntent\LaravelCommon\Database;

use LiveIntent\LaravelCommon\Auth\User;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends \LiveIntent\LaravelCommon\Database\AbstractFactory<TModel>
 *
 * @psalm-suppress ImplementedParamTypeMismatch
 * @method \LiveIntent\LaravelCommon\Auth\User create(array $attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 */
class UserFactory extends AbstractFactory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            //
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            $user->permission_type ??= 'standard';
        });
    }

    /**
     * Set the user to be an admin user.
     */
    public function asAdmin()
    {
        return $this->state(fn () => ['permission_type' => 'admin']);
    }

    /**
     * Set the user to be an internal user.
     */
    public function asInternal()
    {
        return $this->state(fn () => ['permission_type' => 'internal']);
    }

    /**
     * Set the user to be a standard user.
     */
    public function asStandard()
    {
        return $this->state(fn () => ['permission_type' => 'standard']);
    }
}
