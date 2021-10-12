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
        'auth' => [
            'url' => env('LI_AUTH_URL'),
        ],

        'gateway' => [
            'url' => env('LI_GATEWAY_URL'),
        ],

        'portal' => [
            'url' => env('LI_PORTAL_URL'),
        ],

        'nofitications' => [
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
    'auth' => [],
];
