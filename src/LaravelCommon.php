<?php

namespace LiveIntent\LaravelCommon;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Events\RequestHandled;

class LaravelCommon
{
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
