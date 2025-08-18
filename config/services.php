<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'bert_hemopep60' => [
        'url' => env('BERT_HEMOPEP60_MICROSERVICE_BASE_URL', 'http://localhost:9001'),
    ],

    'ampep' => [
        'url' => env('AMPEP_MICROSERVICE_BASE_URL', 'http://host.docker.internal:8001'),
        'timeout' => env('AMPEP_MICROSERVICE_TIMEOUT', 3600),
    ],

    'deepampep30' => [
        'url' => env('DEEPAMPEP30_MICROSERVICE_BASE_URL', 'http://host.docker.internal:8002'),
    ],

    'rfampep30' => [
        'url' => env('DEEPAMPEP30_MICROSERVICE_BASE_URL', 'http://host.docker.internal:8002'),
    ],

    'amp_regression_ec_sa_predict' => [
        'url' => env('AMP_REGRESSION_EC_SA_PREDICT_BASE_URL', 'http://host.docker.internal:8889'),
    ],
];
