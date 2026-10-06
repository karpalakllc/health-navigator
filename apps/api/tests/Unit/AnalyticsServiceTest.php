<?php

namespace Tests\Unit;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_search_queries_aggregates_normalized_terms(): void
    {
        $service = app(AnalyticsService::class);

        $service->recordSearchTerm('Cardiologist');
        $service->recordSearchTerm('cardiologist');
        $service->recordSearchTerm('a');

        $top = $service->topSearchQueries(30, 5);

        $this->assertCount(1, $top);
        $this->assertSame('cardiologist', $top[0]['query']);
        $this->assertSame(2, $top[0]['total']);
    }

    public function test_event_count_by_day_groups_events(): void
    {
        $service = app(AnalyticsService::class);

        AnalyticsEvent::query()->create([
            'event' => 'user.registered',
            'occurred_at' => now(),
        ]);

        $rows = $service->eventCountByDay('user.registered', 7);

        $this->assertGreaterThanOrEqual(1, $rows->count());
        $this->assertSame(1, (int) $rows->last()->total);
    }
}
