<?php

namespace LiveIntent\LaravelCommon\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use LiveIntent\LaravelCommon\LaravelCommonServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LiveIntent\\LaravelCommon\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelCommonServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-common_table.php.stub';
        $migration->up();
        */
    }
}
