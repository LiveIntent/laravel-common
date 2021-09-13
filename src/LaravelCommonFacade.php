<?php

namespace LiveIntent\LaravelCommon;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LiveIntent\LaravelCommon\LaravelCommon
 */
class LaravelCommonFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-common';
    }
}
