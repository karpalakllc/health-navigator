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
    | Web tier
    |--------------------------------------------------------------------------
    |
    | Shared secret the Next.js server sends as X-Web-Tier-Auth so the API will
    | accept its X-Client-IP (see TrustWebTierClientIp). Must match the web
    | tier's WEB_TIER_SECRET and be at least 32 characters; anything shorter is
    | ignored, which leaves client-IP resolution to TrustProxies alone.
    |
    */

    'web_tier' => [
        'secret' => env('WEB_TIER_SECRET'),
    ],

];
