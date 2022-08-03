<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\Database\Factories;

use LiveIntent\LaravelCommon\Database\AbstractFactory;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\User;

/**
 * @extends \LiveIntent\LaravelCommon\Database\AbstractFactory<\App\Models\User>
 */
class UserFactory extends AbstractFactory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'password' => 'foobar'
        ];
    }
}
