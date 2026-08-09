<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public web application URL
    |--------------------------------------------------------------------------
    |
    | Used in transactional emails (password reset, moderation notices).
    | No trailing slash.
    |
    */

    'frontend_url' => env('FRONTEND_URL', env('WEB_PUBLIC_URL', 'http://127.0.0.1:3000')),

    /** @deprecated Use frontend_url; kept for Filament PublicWebUrl. */
    'web_public_url' => env('WEB_PUBLIC_URL', env('FRONTEND_URL', 'http://127.0.0.1:3000')),

    /*
    |--------------------------------------------------------------------------
    | Platform administrator
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan platform:bootstrap` to create the first admin on a
    | fresh deployment. Read through config (not env()) so the command keeps
    | working after `config:cache`. No password default: the command refuses to
    | create an admin rather than inventing a guessable one.
    |
    */

    'admin' => [
        'email' => env('PLATFORM_ADMIN_EMAIL', 'admin@zdravje360.test'),
        'password' => env('PLATFORM_ADMIN_PASSWORD'),
    ],

];
