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

    /*
    |--------------------------------------------------------------------------
    | Local demo seeding
    |--------------------------------------------------------------------------
    |
    | Demo/directory seeders run in local, development and testing, or anywhere
    | SEED_LOCAL_DEMO=true. The staff/member accounts below are created only by
    | PlatformUserSeeder in those environments. `platform:preflight` fails a
    | deploy that leaves local_demo on.
    |
    */

    'seed' => [
        'local_demo' => filter_var(env('SEED_LOCAL_DEMO', false), FILTER_VALIDATE_BOOLEAN),
        'moderator' => [
            'email' => env('PLATFORM_MODERATOR_EMAIL', 'moderator@zdravje360.test'),
            'password' => env('PLATFORM_MODERATOR_PASSWORD', 'password'),
        ],
        'member' => [
            'email' => env('PLATFORM_MEMBER_EMAIL', 'member@zdravje360.test'),
            'password' => env('PLATFORM_MEMBER_PASSWORD', 'password'),
        ],
    ],

];
