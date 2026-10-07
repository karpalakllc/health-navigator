<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The database cache store deletes an expired row only when the same key is
 * read again, so a one-time visitor's rate-limiter rows would stay for good.
 * The hourly purge is what makes "limiter keys last at most an hour (plus
 * one)" true.
 */
class PurgeExpiredCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_expired_cache_and_lock_rows_only(): void
    {
        $now = now()->getTimestamp();

        DB::table('cache')->insert([
            ['key' => 'expired', 'value' => 'i:1;', 'expiration' => $now - 10],
            ['key' => 'expires-now', 'value' => 'i:1;', 'expiration' => $now],
            ['key' => 'live', 'value' => 'i:1;', 'expiration' => $now + 3600],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'old-lock', 'owner' => 'a', 'expiration' => $now - 10],
            ['key' => 'held-lock', 'owner' => 'b', 'expiration' => $now + 60],
        ]);

        $this->artisan('cache:purge-expired')->assertSuccessful();

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['held-lock'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_it_runs_every_hour(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'cache:purge-expired'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
    }
}
