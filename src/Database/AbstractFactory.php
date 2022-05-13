<?php

namespace LiveIntent\LaravelCommon\Database;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
abstract class AbstractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return int
     */
    public function nextId()
    {
        return $this->newModel()->max('id') + 1;
    }
}
