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

    /*
    |--------------------------------------------------------------------------
    | Operational alerts
    |--------------------------------------------------------------------------
    |
    | Where NotifyOnFailedJob mails a failed queued job (verification and reset
    | mail, moderation notices, search indexing). Unset sends nothing, and
    | `platform:preflight` warns about it on a deployment. One alert per job
    | class and exception class per throttle window, so a broken mail server
    | does not answer a storm of failures with a storm of mail.
    |
    */

    'alerts' => [
        'email' => env('PLATFORM_ALERT_EMAIL'),
        'failed_job_throttle_minutes' => (int) env('PLATFORM_ALERT_THROTTLE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content report alerts
    |--------------------------------------------------------------------------
    |
    | `reports:alert-staff` (every 10 minutes) emails everyone who can see the
    | report queue (content_reports.view) about reports that arrived since the
    | last run: one summary per run, never one mail per report. `email` adds
    | a shared inbox to those recipients; unset, only staff are mailed.
    |
    */

    'reports' => [
        'alert_email' => env('REPORT_ALERT_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slow-query logging
    |--------------------------------------------------------------------------
    |
    | ObservabilityServiceProvider logs a warning for any single query slower
    | than `slow_query_ms`, and once per request/job whose queries add up to
    | more than `slow_request_queries_ms`. 0 turns either off. SQL is logged
    | without bindings, so no user input reaches the log.
    |
    */

    'observability' => [
        'slow_query_ms' => (int) env('DB_SLOW_QUERY_MS', 500),
        'slow_request_queries_ms' => (int) env('DB_SLOW_REQUEST_QUERIES_MS', 2000),
    ],

];
