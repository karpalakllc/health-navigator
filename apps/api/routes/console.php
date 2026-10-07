<?php

use App\Models\ProfileCorrection;
use App\Models\UsernameHistory;
use App\Support\DataOps\ImportAlerter;
use App\Support\DataOps\ImportSchedule;
use Illuminate\Support\Facades\Schedule;

Schedule::command('triage:purge-old-sessions')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('analytics:purge-old-events')
    ->dailyAt('03:45')
    ->onOneServer()
    ->withoutOverlapping();

// Anonymous „Дали ви помогна?“ and step counters (docs/urgent-care.md).
Schedule::command('feedback:purge-old')
    ->dailyAt('03:50')
    ->onOneServer()
    ->withoutOverlapping();

// The database cache store never deletes an expired row nobody reads again,
// and rate-limiter keys are derived from visitor addresses: purge them hourly
// so none outlives its window by more than an hour (docs/data-inventory.md).
Schedule::command('cache:purge-expired')
    ->hourly()
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

// Sign-ups never verified within zdravje.accounts.unverified_prune_days (7).
Schedule::command('accounts:prune-unverified')
    ->dailyAt('04:40')
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

// W6-C: one summary of new profile corrections and objections at most every
// 10 minutes, as for reports; the 15/30-day answer targets run from receipt.
Schedule::command('corrections:alert-staff')
    ->everyTenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

// Closed correction requests go zdravje.corrections.retention_days (365)
// after they were closed; open ones stay.
Schedule::command('model:prune', ['--model' => [ProfileCorrection::class]])
    ->dailyAt('04:50')
    ->onOneServer()
    ->withoutOverlapping();

// Import bookkeeping retention: diff summaries, closed review items and
// lifted suppressions go import.retention_days (365) later.
Schedule::command('import:prune')
    ->dailyAt('05:15')
    ->onOneServer()
    ->withoutOverlapping();

// W6-C: source imports (docs/data-import.md). Both are OFF until the owner
// sets IMPORT_FZOM_SCHEDULE / IMPORT_KOMORA_SCHEDULE, and each is skipped
// while its command is not installed. A failed run mails the import alert
// inbox; a finished run reports its counts through ImportRunFinished.
// ФЗОМ: weekly, Monday early morning (the XML is regenerated daily).
Schedule::command(ImportSchedule::command('fzom'))
    ->weeklyOn(1, '05:30')
    ->onOneServer()
    ->withoutOverlapping(180)
    ->when(fn (): bool => ImportSchedule::shouldRun('fzom'))
    ->onFailure(fn () => app(ImportAlerter::class)->scheduledRunFailed('fzom', ImportSchedule::command('fzom')));

// Лекарска комора: monthly on the 3rd (the list changes about every four
// months; the command re-imports only when the published list changed).
Schedule::command(ImportSchedule::command('komora'))
    ->monthlyOn(3, '06:00')
    ->onOneServer()
    ->withoutOverlapping(180)
    ->when(fn (): bool => ImportSchedule::shouldRun('komora'))
    ->onFailure(fn () => app(ImportAlerter::class)->scheduledRunFailed('komora', ImportSchedule::command('komora')));

// W7-A: the verification engine re-evaluates every profile nightly (it also
// runs after each import apply), so an expired licence loses its badge on
// the day it expires. Always on: it reads and writes only the database.
Schedule::command('import:adjudicate')
    ->dailyAt('05:50')
    ->onOneServer()
    ->withoutOverlapping(120);
