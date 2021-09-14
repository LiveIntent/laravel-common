<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Database\Console\Factories\FactoryMakeCommand as BaseFactoryMakeCommand;

class FactoryMakeCommand extends BaseFactoryMakeCommand
{
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return __DIR__.$stub;
    }
}
