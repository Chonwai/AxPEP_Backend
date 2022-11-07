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

    'paths' => ['api/*', 'api/v1'],

    'allowed_methods' => ['POST', 'GET', 'DELETE', 'PUT'],

    'allowed_origins' => ['http://159.203.14.63', 'http://localhost:3000', 'http://159.203.14.63:80', 'http://localhost:8000', 'http://stage.cbbio.online:80', 'https://stage.cbbio.online'],

    'allowed_origins_patterns' => ['Google'],

    'allowed_headers' => ['X-Custom-Header', 'Upgrade-Insecure-Requests'],

    'exposed_headers' => false,

    'max_age' => false,

    'supports_credentials' => false,
];
