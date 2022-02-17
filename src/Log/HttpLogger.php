<?php

namespace LiveIntent\LaravelCommon\Log;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Events\RequestHandled;

class HttpLogger
{
    /**
     * The list of hidden request headers.
     *
     * @var array
     */
    private $hiddenRequestHeaders = [];

    /**
     * The list of hidden request parameters.
     *
     * @var array
     */
    private $hiddenRequestParameters = [];

    /**
     * The list of obfuscated request parameters.
     *
     * @var array
     */
    private $obfuscatedRequestHeaders = [];

    /**
     * The list of obfuscated request parameters.
     *
     * @var array
     */
    private $obfuscatedRequestParameters = [];

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->hiddenRequestHeaders = config('liveintent.logging.hidden_request_headers', []);
        $this->hiddenRequestParameters = config('liveintent.logging.hidden_request_parameters', []);
        $this->obfuscatedRequestHeaders = config('liveintent.logging.obfuscated_request_headers', []);
        $this->obfuscatedRequestParameters = config('liveintent.logging.obfuscated_request_parameters', []);
    }

    /**
     * Log an HTTP request handled by the server.
     *
     * Most of this code is shamelessly stolen from: https://github.com/laravel/telescope/blob/4.x/src/Watchers/RequestWatcher.php
     */
    public function logRequest(RequestHandled $event)
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $event->request->server('REQUEST_TIME_FLOAT');

        info('RequestHandled', [
            'ip_address' => $event->request->ip(),
            'uri' => str_replace($event->request->root(), '', $event->request->fullUrl()) ?: '/',
            'method' => $event->request->method(),
            'controller_action' => optional($event->request->route())->getActionName(),
            'middleware' => array_values(optional($event->request->route())->gatherMiddleware() ?? []),
            'headers' => $this->headers($event->request->headers->all()),
            'payload' => $this->payload($this->input($event->request)),
            'session' => $this->payload($this->sessionVariables($event->request)),
            'response_status' => $event->response->getStatusCode(),
            'duration' => $startTime ? floor((microtime(true) - $startTime) * 1000) : null,
            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]);
    }

    /**
     * Format the given headers.
     *
     * @param  array  $headers
     * @return array
     */
    protected function headers($headers)
    {
        $headers = collect($headers)->map(function ($header) {
            return $header[0];
        })->toArray();

        return $this->hideSensitiveValues($headers);
    }

    /**
     * Format the given payload.
     *
     * @param  array  $payload
     * @return array
     */
    protected function payload($payload)
    {
        return $this->hideSensitiveValues($payload);
    }

    /**
     * Hide or obfuscate sensitive values.
     *
     * @param array $data
     * @return array
     */
    public function hideSensitiveValues($data)
    {
        foreach ($this->obfuscatedRequestParameters as $parameter) {
            if ($value = Arr::get($data, $parameter)) {
                Arr::set($data, $parameter, $this->obfuscate($value));
            }
        }

        foreach ($this->obfuscatedRequestHeaders as $parameter) {
            if ($value = Arr::get($data, $parameter)) {
                Arr::set($data, $parameter, $this->obfuscate($value));
            }
        }

        foreach ($this->hiddenRequestHeaders as $parameter) {
            if (Arr::get($data, $parameter)) {
                Arr::set($data, $parameter, '******');
            }
        }

        foreach ($this->hiddenRequestParameters as $parameter) {
            if (Arr::get($data, $parameter)) {
                Arr::set($data, $parameter, '******');
            }
        }

        return $data;
    }

    /**
     * Obfuscate a potentially sensitive value.
     *
     * @param string $value
     * @return string
     */
    public function obfuscate($value)
    {
        return str($value ?: '')->limit(8, '******')->toString();
    }

    /**
     * Extract the input from the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function input(Request $request)
    {
        $files = $request->files->all();

        array_walk_recursive($files, function (&$file) {
            $file = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->isFile() ? ($file->getSize() / 1000).'KB' : '0',
            ];
        });

        return array_replace_recursive($request->input(), $files);
    }

    /**
     * Extract the session variables from the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function sessionVariables(Request $request)
    {
        return $request->hasSession() ? $request->session()->all() : [];
    }
}
