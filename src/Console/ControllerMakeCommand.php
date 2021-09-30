<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Routing\Console\ControllerMakeCommand as BaseControllerMakeCommand;

class ControllerMakeCommand extends BaseControllerMakeCommand
{
    use OverridesStubs;
}
