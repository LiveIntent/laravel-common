<?php

namespace LiveIntent\LaravelCommon;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Foundation\Http\Events\RequestHandled;
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

        $this->registerHealthCheck();
        $this->registerHttpLogger();
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
            ->hasConfigFile('liveintent')
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

    /**
     * Register http logger
     */
    protected function registerHttpLogger()
    {
        /** @psalm-suppress UndefinedClass */
        if ($logger = config('liveintent.logging.logger')) {
            $this->app['events']->listen(RequestHandled::class, [
                new $logger(), 'logRequest',
            ]);
        }
    }

    /**
     * Register the health check endpoint.
     *
     * Override if need be by re-registering the route in your app.
     */
    protected function registerHealthCheck()
    {
        Route::get('/health', fn () => response()->json());

        Queue::looping(fn () => touch('/tmp/healthcheck'));
    }
}
