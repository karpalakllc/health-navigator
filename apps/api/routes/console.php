<?php

use App\Models\UsernameHistory;
use Illuminate\Support\Facades\Schedule;

Schedule::command('triage:purge-old-sessions')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('analytics:purge-old-events')
    ->dailyAt('03:45')
    ->onOneServer()
    ->withoutOverlapping();

// One summary of newly arrived reports at most every 10 minutes, so the
// 24-hour review goal in the terms does not depend on someone opening the panel.
Schedule::command('reports:alert-staff')
    ->everyTenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('moderation:send-digest')
    ->dailyAt('07:00')
    ->onOneServer()
    ->withoutOverlapping();

// Expired Sanctum tokens are already refused; this only stops the table growing.
// The 24h grace keeps just-expired rows around when debugging a failed sign-in.
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('04:15')
    ->onOneServer()
    ->withoutOverlapping();

// Failed jobs stay inspectable and retryable (queue:failed / queue:retry) for
// 30 days, then go: their payloads can carry an address or a notice's text.
// NotifyOnFailedJob has alerted on each long before then.
Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('04:30')
    ->onOneServer()
    ->withoutOverlapping();

// Released usernames are held back from others for six months, then the
// private record of them goes too.
Schedule::command('model:prune', ['--model' => [UsernameHistory::class]])
    ->dailyAt('04:45')
    ->onOneServer()
    ->withoutOverlapping();

// Audit log retention (docs/data-inventory.md): entries older than
// activitylog.clean_after_days (365) are deleted.
Schedule::command('activitylog:clean --force')
    ->dailyAt('04:45')
    ->onOneServer()
    ->withoutOverlapping();
