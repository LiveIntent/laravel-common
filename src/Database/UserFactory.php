<?php

namespace LiveIntent\LaravelCommon\Database;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends \LiveIntent\LaravelCommon\Database\AbstractFactory<TModel>
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
            'email' => $this->faker->unique()->safeEmail(),
            'permission_type' => 'standard',
        ];
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
