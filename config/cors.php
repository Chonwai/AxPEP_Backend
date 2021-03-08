<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     * You can enable CORS for 1 or multiple paths.
     * Example: ['api/*']
     */
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*', 'http://159.203.14.63'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => false,

    'max_age' => false,

    'supports_credentials' => false,

];
