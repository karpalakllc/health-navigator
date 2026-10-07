<?php

namespace Tests\Feature\Console;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The purge is the only thing standing between the platform and indefinite
 * retention of search queries — which on this platform are symptom text tied to
 * a user id. An untested scheduled command that silently does nothing is worse
 * than no command, because it looks like a retention policy.
 */
class PurgeOldAnalyticsEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $occurredAt, string $name = 'search.query'): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'event' => $name,
            'properties' => ['q' => 'кардиолог'],
            'occurred_at' => Carbon::parse($occurredAt),
        ]);
    }

    public function test_it_deletes_events_past_the_retention_window(): void
    {
        $old = $this->event(now()->subDays(200)->toDateTimeString());
        $recent = $this->event(now()->subDays(10)->toDateTimeString());

        $this->artisan('analytics:purge-old-events')->assertSuccessful();

        $this->assertDatabaseMissing('analytics_events', ['id' => $old->id]);
        $this->assertDatabaseHas('analytics_events', ['id' => $recent->id]);
    }

    public function test_the_window_is_configurable(): void
    {
        $event = $this->event(now()->subDays(10)->toDateTimeString());

        $this->artisan('analytics:purge-old-events', ['--days' => 5])->assertSuccessful();

        $this->assertDatabaseMissing('analytics_events', ['id' => $event->id]);
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $event = $this->event(now()->subDays(200)->toDateTimeString());

        $this->artisan('analytics:purge-old-events', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);
    }

    public function test_it_succeeds_with_nothing_to_purge(): void
    {
        $this->event(now()->subDay()->toDateTimeString());

        $this->artisan('analytics:purge-old-events')->assertSuccessful();

        $this->assertSame(1, AnalyticsEvent::query()->count());
    }

    public function test_it_deletes_beyond_a_single_batch(): void
    {
        // The command deletes in batches of 5000; prove the loop terminates and
        // clears everything rather than stopping after the first pass.
        $rows = [];
        foreach (range(1, 60) as $i) {
            $rows[] = [
                'event' => 'search.query',
                'properties' => json_encode(['q' => "term{$i}"]),
                'occurred_at' => now()->subDays(200),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        AnalyticsEvent::query()->insert($rows);

        $this->artisan('analytics:purge-old-events')->assertSuccessful();

        $this->assertSame(0, AnalyticsEvent::query()->count());
    }

    private function uxCounters(string $day): void
    {
        DB::table('ux_heatmap_cells')->insert([
            'day' => $day, 'route' => '/', 'viewport_class' => 'mobile', 'width_bucket' => 320,
            'x_bucket' => 1, 'y_bucket' => 1, 'clicks' => 1, 'dead_clicks' => 0, 'rage_clicks' => 0,
        ]);
        DB::table('ux_element_stats')->insert([
            'day' => $day, 'route' => '/', 'viewport_class' => 'mobile', 'target_key' => 'main/link',
            'clicks' => 1, 'dead_clicks' => 0, 'rage_clicks' => 0,
        ]);
        DB::table('ux_page_stats')->insert([
            'day' => $day, 'route' => '/', 'viewport_class' => 'mobile', 'views' => 1,
        ]);
    }

    public function test_it_deletes_ux_counters_after_180_days(): void
    {
        $this->uxCounters(now()->subDays(181)->toDateString());
        $this->uxCounters(now()->subDays(179)->toDateString());

        $this->artisan('analytics:purge-old-events')->assertSuccessful();

        foreach (['ux_heatmap_cells', 'ux_element_stats', 'ux_page_stats'] as $table) {
            $this->assertSame(1, DB::table($table)->count(), $table);
            $this->assertSame(
                now()->subDays(179)->toDateString(),
                Carbon::parse((string) DB::table($table)->value('day'))->toDateString(),
            );
        }
    }

    public function test_the_ux_window_is_configurable_and_dry_run_keeps_rows(): void
    {
        $this->uxCounters(now()->subDays(10)->toDateString());

        $this->artisan('analytics:purge-old-events', ['--ux-days' => 5, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('ux_page_stats')->count());

        $this->artisan('analytics:purge-old-events', ['--ux-days' => 5])->assertSuccessful();
        $this->assertSame(0, DB::table('ux_page_stats')->count());
        $this->assertSame(0, DB::table('ux_heatmap_cells')->count());
    }
}
