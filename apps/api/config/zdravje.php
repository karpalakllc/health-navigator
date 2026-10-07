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
    | Staff e-mails excused from the panel's two-factor requirement — honoured
    | only when APP_ENV is "local" (never development, staging or production),
    | so the owner's local demo can skip the code prompt. Preflight refuses a
    | deployment that sets it. Comma-separated.
    */
    'mfa' => [
        'local_exempt_emails' => array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) env('STAFF_MFA_LOCAL_EXEMPT_EMAILS', '')),
        ))),
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

    /*
    |--------------------------------------------------------------------------
    | Legal texts
    |--------------------------------------------------------------------------
    |
    | The version of the Terms of Use a member accepts at sign-up, stored with
    | the acceptance (users.terms_version). The last-updated date of the terms
    | page on the web (apps/web/src/content/legal/terms.tsx): change both
    | together when the terms change.
    |
    */

    'legal' => [
        'terms_version' => '2026-10-06',
    ],

    /*
    |--------------------------------------------------------------------------
    | Never-verified sign-ups
    |--------------------------------------------------------------------------
    |
    | `accounts:prune-unverified` (daily) deletes client sign-ups whose address
    | was never verified, this many days after the sign-up — or after the last
    | time a second sign-up contested the address, whichever is later. Their
    | requested usernames were never held, but the rows (name, address, hashed
    | password) are personal data kept for no purpose.
    |
    */

    'accounts' => [
        'unverified_prune_days' => (int) env('UNVERIFIED_ACCOUNT_PRUNE_DAYS', 7),
    ],

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
    | Profile corrections and objections
    |--------------------------------------------------------------------------
    |
    | `corrections:alert-staff` (every 10 minutes) emails everyone who can see
    | the corrections queue (profile_corrections.view) about requests that
    | arrived since the last run, one summary per run. `alert_email` adds a
    | shared inbox. Closed requests are deleted `retention_days` after they
    | were closed (model:prune, daily); open ones are never pruned.
    |
    */

    'corrections' => [
        'alert_email' => env('CORRECTION_ALERT_EMAIL'),
        'retention_days' => (int) env('CORRECTION_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile reports („Пријави профил“)
    |--------------------------------------------------------------------------
    |
    | Reports about a whole doctor, facility or pharmacy profile share the
    | corrections queue (type `report`). A profile with `priority_threshold`
    | or more independent open reports floats to the top of that queue;
    | nothing is ever hidden automatically. A guest can report one profile
    | once a day per address (the address is kept only as a keyed hash, in the
    | cache for that day and on the open report so independent reporters can
    | be told apart; closing the report erases it). Closed reports are deleted
    | `retention_days` after they were closed.
    |
    */

    'profile_reports' => [
        'priority_threshold' => max(2, (int) env('PROFILE_REPORT_PRIORITY_THRESHOLD', 3)),
        'retention_days' => max(30, (int) env('PROFILE_REPORT_RETENTION_DAYS', 90)),
    ],

    /*
    |--------------------------------------------------------------------------
    | ALTCHA (self-hosted proof of work against bots)
    |--------------------------------------------------------------------------
    |
    | Forms open to automated abuse (sign-up, reports, corrections,
    | objections, claim requests) need a solved ALTCHA challenge
    | (App\Support\Altcha\AltchaGuard, middleware `altcha`). Challenges are
    | issued by GET /v1/altcha/challenge, signed with `hmac_key`, solved in
    | the visitor's browser (PBKDF2/SHA-256) and verified here; nothing goes
    | to a third party and nothing is stored except the spent challenge's
    | hash, in the cache until the challenge expires (replay protection).
    |
    | `hmac_key` defaults to a key derived from APP_KEY. Expected browser work
    | is about `cost` × (counter_min + counter_max) / 2 PBKDF2 rounds: the
    | defaults take well under a second on a laptop and one to three seconds
    | on an older phone. A solution is refused when it comes back less than
    | `min_fill_seconds` after its challenge was issued (no person fills a
    | form that fast). `enabled` exists for the test suite; keep it on.
    |
    */

    'altcha' => [
        'enabled' => (bool) env('ALTCHA_ENABLED', true),
        'hmac_key' => env('ALTCHA_HMAC_KEY'),
        'cost' => max(1, (int) env('ALTCHA_COST', 1000)),
        'counter_min' => max(0, (int) env('ALTCHA_COUNTER_MIN', 1000)),
        'counter_max' => max(1, (int) env('ALTCHA_COUNTER_MAX', 4000)),
        'expires_minutes' => max(1, (int) env('ALTCHA_EXPIRES_MINUTES', 30)),
        'min_fill_seconds' => max(0, (int) env('ALTCHA_MIN_FILL_SECONDS', 2)),
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
