<?php

namespace LiveIntent\LaravelCommon\Log;

use Illuminate\Log\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Illuminate\Foundation\Http\Events\RequestHandled;

class HttpLogger
{
    /**
     * The list of paths to exclude from logging.
     *
     * @var array
     */
    private $ignorePaths = [];

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->ignorePaths = config('liveintent.logging.ignore_paths', []);
    }

    /**
     * Used with `tap` on the monolog logging configuration like so:
     *
     *   'stderr' => [
     *        'driver' => 'monolog',
     *        'level' => env('LOG_LEVEL', 'debug'),
     *        'handler' => StreamHandler::class,
     *        'tap' => [
     *            \LiveIntent\LaravelCommon\Log\HttpLogger::class,
     *        ],
     *        'formatter' => env('LOG_STDERR_FORMATTER', \Monolog\Formatter\JsonFormatter::class),
     *        'with' => [
     *            'stream' => 'php://stderr',
     *        ],
     *    ]
     */
    public function __invoke(Logger $logger)
    {
        // Configure logging to include files and line numbers
        $introspection = new IntrospectionProcessor(
            \Monolog\Logger::DEBUG,
            [
                'Monolog\\',
                'Illuminate\\',
            ]
        );

        // Configure LiveIntent logging standardization
        $liveIntent = new LiveIntentLogProcessor();

        $logger->pushProcessor($introspection);
        $logger->pushProcessor($liveIntent);
    }

    /**
     * Log an HTTP request handled by the server.
     *
     * Most of this code is shamelessly stolen from: https://github.com/laravel/telescope/blob/4.x/src/Watchers/RequestWatcher.php
     */
    public function logRequest(RequestHandled $event)
    {
        if ($event->request->is($this->ignorePaths)) {
            return;
        }

        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $event->request->server('REQUEST_TIME_FLOAT');

        info('RequestHandled', [
            'ip_address' => $event->request->ip(),
            'uri' => str_replace($event->request->root(), '', $event->request->fullUrl()) ?: '/',
            'method' => $event->request->method(),
            'controller_action' => optional($event->request->route())->getActionName(),
            'middleware' => array_values(optional($event->request->route())->gatherMiddleware() ?? []),
            'user_id' => $event->request->user()?->id,
            'response_status' => $event->response->getStatusCode(),
            'duration' => $startTime ? floor((microtime(true) - $startTime) * 1000) : null,
            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]);
    }
}
