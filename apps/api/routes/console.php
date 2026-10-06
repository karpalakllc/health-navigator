<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('triage:purge-old-sessions')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('analytics:purge-old-events')
    ->dailyAt('03:45')
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
