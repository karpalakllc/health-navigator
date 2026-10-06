<?php

namespace Tests\Unit;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * The dashboard aggregates are reused for AGGREGATE_TTL_SECONDS: a repeat
     * call inside the window runs no query, and one after it sees new events.
     */
    public function test_dashboard_aggregates_are_cached_for_five_minutes(): void
    {
        $service = app(AnalyticsService::class);
        AnalyticsEvent::query()->create(['event' => 'user.registered', 'occurred_at' => now()]);
        AnalyticsEvent::query()->create(['event' => 'search.query', 'properties' => ['q' => 'кардиолог'], 'occurred_at' => now()]);

        $first = [
            $service->summaryForDays(30),
            $service->registrationsByDay(14)->all(),
            $service->topSearchQueries(30, 10),
        ];

        AnalyticsEvent::query()->create(['event' => 'user.registered', 'occurred_at' => now()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $second = [
            $service->summaryForDays(30),
            $service->registrationsByDay(14)->all(),
            $service->topSearchQueries(30, 10),
        ];
        DB::disableQueryLog();

        $this->assertSame([], DB::getQueryLog());
        $this->assertEquals($first, $second);
        $this->assertSame(1, $second[0]['registrations']);

        $this->travel(AnalyticsService::AGGREGATE_TTL_SECONDS + 1)->seconds();

        $this->assertSame(2, $service->summaryForDays(30)['registrations']);
        $this->assertSame(2, $service->registrationsByDay(14)->last()->total);
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
