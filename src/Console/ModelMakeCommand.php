<?php

namespace LiveIntent\LaravelCommon\Console;

use Illuminate\Foundation\Console\ModelMakeCommand as BaseModelMakeCommand;

class ModelMakeCommand extends BaseModelMakeCommand
{
    use OverridesStubs;
}
