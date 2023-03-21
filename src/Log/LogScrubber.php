<?php

namespace LiveIntent\LaravelCommon\Log;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;

class LogScrubber
{
    /**
     * The list of hidden request headers.
     */
    private array $hiddenRequestHeaders;

    /**
     * The list of hidden request parameters.
     */
    private array $hiddenRequestParameters;

    /**
     * The list of obfuscated request parameters.
     */
    private array $obfuscatedRequestHeaders;

    /**
     * The list of obfuscated request parameters.
     */
    private array $obfuscatedRequestParameters;

    /**
     * Create a new instance.
     *
     * @return void
     */
    private function __construct()
    {
        $this->hiddenRequestHeaders = config('liveintent.logging.hidden_request_headers', []);
        $this->hiddenRequestParameters = config('liveintent.logging.hidden_request_parameters', []);
        $this->obfuscatedRequestHeaders = config('liveintent.logging.obfuscated_request_headers', []);
        $this->obfuscatedRequestParameters = config('liveintent.logging.obfuscated_request_parameters', []);
    }

    public static function singleton(): LogScrubber
    {
        return new LogScrubber();
    }

    /**
     * Hide or obfuscate sensitive values.
     */
    public function hideSensitiveValues(array $data): array
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
     */
    public static function obfuscate(string $value): string
    {
        return str($value ?: '')->limit(8, '******')->toString();
    }
}
