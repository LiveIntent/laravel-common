<?php

namespace LiveIntent\LaravelCommon\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider;
use LiveIntent\LaravelCommon\LaravelCommonServiceProvider;
use LiveIntent\LaravelCommon\Testing\InteractsWithJSONResponses;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    use InteractsWithJSONResponses;

    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LiveIntent\\LaravelCommon\\Tests\\Fixtures\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->registerResponseMacros();
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/database/migrations');
    }

    protected function defineRoutes($router)
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/Fixtures/routes/api.php');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function getPackageProviders($app)
    {
        return [
            JsonApiPaginateServiceProvider::class,
            LaravelCommonServiceProvider::class,
        ];
    }
}
