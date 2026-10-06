<?php

use App\Models\Activity;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

/*
 * The audit log (docs/data-inventory.md §1, `activity_log`). It records who
 * did what to doctor profiles, change requests, review replies, reports and
 * account suspensions. It never stores an IP address or user agent, and the
 * attributes below are never written into it, whatever model logs them.
 */
return [

    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * `activitylog:clean` (scheduled daily in routes/console.php) deletes rows
     * older than this. A year covers the notice-and-action record-keeping
     * recommendation (docs/legal/research-memo.md §4.5).
     */
    'clean_after_days' => 365,

    'default_log_name' => 'default',

    'default_auth_driver' => null,

    'include_soft_deleted_subjects' => true,

    'activity_model' => Activity::class,

    'default_except_attributes' => [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'email_verified_at',
        'updated_at',
        'created_at',
    ],

    'buffer' => [
        'enabled' => false,
    ],

    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
