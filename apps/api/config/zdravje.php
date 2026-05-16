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

];
