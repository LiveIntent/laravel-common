<?php

namespace LiveIntent\LaravelCommon\Providers;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use LiveIntent\LaravelCommon\Auth\Guards\LITokenGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class LIAuthServiceProvider extends ServiceProvider
{
    public function __construct($app)
    {
        parent::__construct($app);
        $this->registerEnvironmentVariables();
    }

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCustomGuards();
        $this->registerPolicies();
    }

    protected function registerEnvironmentVariables()
    {
        if (config('auth.li_token.keys.public') == null) {
            config(['auth.li_token.keys.public' => env('TESSELLATE_PUBLIC_KEY') ]);
        }

        if (config('auth.guards.li_token') == null) {
            config(['auth.guards.li_token' => [
                'driver' => 'li_token',
                'provider' => 'users',
            ]]);
        }

        $this->policies = config('auth.li_token.policies', []);
    }

    protected function registerCustomGuards()
    {
        Auth::extend('li_token', function ($app, $_name, array $config) {
            throw_if($config['provider'] == null);

            return new RequestGuard(function ($request) use ($config) {
                return (new LITokenGuard(
                    Auth::createUserProvider($config['provider']),
                ))->user($request);
            }, $app['request']);
        });

        // Causes issues with an infinite loop, cannot set `li_token` as default guard with current implementation
//        Auth::shouldUse('li_token');
    }
}
