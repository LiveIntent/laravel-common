<?php

namespace LiveIntent\LaravelCommon\Tests\Fixtures\Database\Factories;

use LiveIntent\LaravelCommon\Database\AbstractFactory;
use LiveIntent\LaravelCommon\Tests\Fixtures\App\Models\Post;

/**
 * @extends \LiveIntent\LaravelCommon\Database\AbstractFactory<\App\Models\Post>
 */
class PostFactory extends AbstractFactory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->words(5, true),
            'body' => $this->faker->text()
        ];
    }
}
