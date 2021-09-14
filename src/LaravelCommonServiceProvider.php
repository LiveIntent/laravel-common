<?php

namespace LiveIntent\LaravelCommon;

use Spatie\LaravelPackageTools\Package;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Migrations\MigrationCreator;
use LiveIntent\LaravelCommon\Console\TestMakeCommand;
use LiveIntent\LaravelCommon\Console\ModelMakeCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LiveIntent\LaravelCommon\Console\FactoryMakeCommand;
use LiveIntent\LaravelCommon\Console\RequestMakeCommand;
use LiveIntent\LaravelCommon\Console\ResourceMakeCommand;
use LiveIntent\LaravelCommon\Console\ControllerMakeCommand;
use LiveIntent\LaravelCommon\Console\ApiResourceMakeCommand;

class LaravelCommonServiceProvider extends PackageServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        // override the migration's custom stub path
        $this->app->singleton('migration.creator', function ($app) {
            return new MigrationCreator($app['files'], __DIR__.'/Console/stubs');
        });
    }

    /**
     * Configure the package.
     *
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-common')
            ->hasConfigFile()
            ->hasCommand(ApiResourceMakeCommand::class)
            ->hasCommand(ControllerMakeCommand::class)
            ->hasCommand(FactoryMakeCommand::class)
            ->hasCommand(ModelMakeCommand::class)
            ->hasCommand(RequestMakeCommand::class)
            ->hasCommand(ResourceMakeCommand::class)
            ->hasCommand(TestMakeCommand::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.controller.make',
            'command.factory.make',
            'command.model.make',
            'command.request.make',
            'command.resource.make',
            'command.test.make',
        ];
    }
}
