<?php

namespace LiveIntent\LaravelCommon\Providers;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use LiveIntent\LaravelCommon\Auth\Guards\LITokenGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCustomGuards();
    }

    private function registerCustomGuards()
    {
        Auth::extend('li_token', function ($app, $_name, array $config) {
            throw_if($config['provider'] == null);

            return new RequestGuard(function ($request) use ($config) {
                return (new LITokenGuard(
                    Auth::createUserProvider($config['provider']),
                ))->user($request);
            }, $app['request']);
        });
    }
}
