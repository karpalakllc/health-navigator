<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ],
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
