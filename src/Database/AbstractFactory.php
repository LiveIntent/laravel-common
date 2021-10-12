<?php

namespace LiveIntent\LaravelCommon\Database;

use Illuminate\Database\Eloquent\Factories\Factory;

abstract class AbstractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function nextId()
    {
        return $this->newModel()->max('id') + 1;
    }
}
