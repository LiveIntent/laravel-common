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
            'public_key' => env('TESSELLATE_PUBLIC_KEY', ''),
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
    */

    'logging' => [
        'logger' => \LiveIntent\LaravelCommon\Log\HttpLogger::class,

        'ignore_paths' => [
            'telescope*',
            'health',
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

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    |
    | For your convenience, LiveIntent offers a wrapper PHP SDK that lets
    | you abstract away most of the boring parts of interacting with a
    | third party api. This section configures the client defaults.
    |
    */

    'client' => [
        // static token to use for authenticating requests
        'personal_access_token' => env('LI_PERSONAL_ACCESS_TOKEN'),

        // number of default retries per request
        'tries' => 3,

        // number of seconds to wait for a response before hangup
        'timeout' => 10,

        // number of seconds to wait between retries
        'retryDelay' => 10,

        // base url of the api
        'base_url' => env('LI_GATEWAY_URL', ''),
    ],

    'search' => [
        'max_nested_depth' => env('LI_SEARCH_MAX_NESTED_DEPTH', 15)
    ]

];

