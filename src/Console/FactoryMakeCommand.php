<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Database\Console\Factories\FactoryMakeCommand as BaseFactoryMakeCommand;

class FactoryMakeCommand extends BaseFactoryMakeCommand
{
    use OverridesStubs;
}
