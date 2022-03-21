<?php

namespace LiveIntent\LaravelCommon;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Events\RequestHandled;
use LiveIntent\LaravelCommon\Auth\Guards\LITokenGuard;

class LaravelCommon
{
    /**
     * Register Tessellate token authentication guard
     */
    public static function registerAuthGuard()
    {
        Auth::extend('li_token', function ($app, $_name, array $config) {
            return new RequestGuard(function ($request) use ($config) {
                return (new LITokenGuard(
                    Auth::createUserProvider($config['provider'])
                ))->user($request);
            }, $app['request']);
        });
    }

    /**
     * Register a logger for HTTP requests.
     *
     * @param string|null $logger
     */
    public static function logHttpRequests($logger = null)
    {
        /** @psalm-suppress UndefinedClass */
        if ($logger = $logger ?: config('liveintent.logging.logger')) {
            app('events')->listen(RequestHandled::class, [
                new $logger(), 'logRequest',
            ]);
        }
    }

    /**
     * Register application health checks.
     *
     * @param string|null $path
     */
    public static function healthChecks()
    {
        static::httpHealthCheck();
        static::queueHealthCheck();
    }

    /**
     * Register an http health check.
     *
     * @param string|null $path
     */
    public static function httpHealthCheck($path = '/health')
    {
        Route::get($path, fn () => response()->json());
    }

    /**
     * Register a queue worker health check
     *
     * @param string|null $path
     */
    public static function queueHealthCheck($path = '/tmp/healthcheck')
    {
        Queue::looping(fn () => touch('/tmp/healthcheck'));
    }
}
