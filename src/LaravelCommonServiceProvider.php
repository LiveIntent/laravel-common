<?php

namespace LiveIntent\LaravelCommon;

use Spatie\LaravelPackageTools\Package;
use LiveIntent\LaravelCommon\Console\TestMakeCommand;
use LiveIntent\LaravelCommon\Console\ModelMakeCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LiveIntent\LaravelCommon\Console\RequestMakeCommand;
use LiveIntent\LaravelCommon\Console\ResourceMakeCommand;
use LiveIntent\LaravelCommon\Console\ControllerMakeCommand;
use LiveIntent\LaravelCommon\Console\ApiResourceMakeCommand;

class LaravelCommonServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-common')
            ->hasConfigFile()
            ->hasCommand(ApiResourceMakeCommand::class)
            ->hasCommand(ControllerMakeCommand::class)
            ->hasCommand(ModelMakeCommand::class)
            ->hasCommand(RequestMakeCommand::class)
            ->hasCommand(ResourceMakeCommand::class)
            ->hasCommand(TestMakeCommand::class);
    }
}
