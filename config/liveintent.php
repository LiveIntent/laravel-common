<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveIntent Services
    |--------------------------------------------------------------------------
    |
    | Assuming your app needs to interact with other LiveIntent services,
    | you will probably want to have a single place to manage all that
    | configuration. This section is meant to fulfill that purpose.
    |
    */

    'services' => [
        'tessellate' => [
            'url' => env('LI_TESSELLATE_URL'),
        ],

        'gateway' => [
            'url' => env('LI_GATEWAY_URL'),
        ],

        'portal' => [
            'url' => env('LI_PORTAL_URL'),
        ],

        'notifications' => [
            'url' => env('LI_NORMANI_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LiveIntent Auth
    |--------------------------------------------------------------------------
    |
    | Here you may configure how your app will authenticate with the rest
    | of the LiveIntent stack. You should also have a look at auth.php
    | which controls the auth mechanisms your app will make use of.
    |
    */
    'auth' => [
        'li_token' => [
            'public_key' => env('TESSELLATE_PUBLIC_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Logging
    |--------------------------------------------------------------------------
    |
    | By default, we will log some common debug information about each of
    | the http requests served by the application. Here, you may tweak
    | the behavior of what is logged and hide any sensitive values.
    |
    */

    'logging' => [
        'logger' => \LiveIntent\LaravelCommon\Log\HttpLogger::class,

        'ignore_paths' => [
            'telescope*',
            'health'
        ],

        'obfuscated_request_headers' => [
            'authorization',
            'cookie',
        ],

        'obfuscated_request_parameters' => [],

        'hidden_request_headers' => [
            'php-auth-pw',
        ],

        'hidden_request_parameters' => [
            'password',
            'password_confirmation',
        ],

    ],

];
