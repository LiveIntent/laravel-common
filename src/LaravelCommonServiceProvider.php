<?php

namespace LiveIntent\LaravelCommon;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LiveIntent\LaravelCommon\Commands\LaravelCommonCommand;

class LaravelCommonServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-common')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-common_table')
            ->hasCommand(LaravelCommonCommand::class);
    }
}
