<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Local dev origins are convenience only — never allow them in production,
    // where CORS_ALLOWED_ORIGINS must list the real web origins. Note: config files
    // are loaded before the environment is detected, so this reads env() directly
    // rather than app()->environment().
    'allowed_origins' => array_values(array_filter(array_merge(
        env('APP_ENV') === 'production' ? [] : [
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
