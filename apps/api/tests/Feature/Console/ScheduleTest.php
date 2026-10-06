<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_expired_sanctum_tokens_are_pruned_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'sanctum:prune-expired'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertStringContainsString('--hours=24', (string) $events[0]->command);
        $this->assertSame('15 4 * * *', $events[0]->expression);
    }
}
