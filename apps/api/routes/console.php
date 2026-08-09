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
