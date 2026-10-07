<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\SearchTermDaily;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analytics events had no retention policy at all, while triage sessions did.
 *
 * Searches are no longer events: they are kept only as anonymous daily
 * aggregates (search_term_daily, no user), which this command also expires on
 * their own, longer window, and the anonymous UX counters (ux_* tables,
 * docs/ux-heatmaps.md) on theirs.
 */
class PurgeOldAnalyticsEventsCommand extends Command
{
    protected $signature = 'analytics:purge-old-events
                            {--days=180 : Delete events older than this many days}
                            {--search-days=365 : Delete daily search-term aggregates older than this many days}
                            {--ux-days= : Delete UX counters older than this many days (default: config ux.retention_days, 180)}
                            {--dry-run : Report counts without deleting}';

    protected $description = 'Delete analytics events (default 180 days), search-term aggregates (default 365 days) and UX counters (default 180 days) past their retention window';

    public function handle(): int
    {
        $this->purgeEvents();
        $this->purgeSearchAggregates();
        $this->purgeUxCounters();

        return self::SUCCESS;
    }

    private function purgeEvents(): void
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $count = AnalyticsEvent::query()->where('occurred_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No analytics events older than {$days} days.");

            return;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$count} analytics event(s) recorded before {$cutoff->toDateString()}.");

            return;
        }

        $deleted = 0;

        // Batched by id rather than per-model: analytics_events has no dependents
        // and no model events, so hydrating each row would be pure overhead.
        do {
            $ids = AnalyticsEvent::query()
                ->where('occurred_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(5000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AnalyticsEvent::query()->whereIn('id', $ids)->delete();
        } while (true);

        $this->info("Deleted {$deleted} analytics event(s) older than {$days} days.");
    }

    private function purgeSearchAggregates(): void
    {
        $days = max(1, (int) $this->option('search-days'));
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        $query = SearchTermDaily::query()->where('date', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No search-term aggregates older than {$days} days.");

            return;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$count} search-term aggregate row(s) dated before {$cutoff}.");

            return;
        }

        $deleted = SearchTermDaily::query()->where('date', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} search-term aggregate row(s) older than {$days} days.");
    }

    private function purgeUxCounters(): void
    {
        $option = $this->option('ux-days');
        $days = max(1, (int) ($option ?? config('ux.retention_days', 180)));
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        foreach (['ux_heatmap_cells', 'ux_element_stats', 'ux_page_stats'] as $table) {
            $count = DB::table($table)->where('day', '<', $cutoff)->count();

            if ($count === 0) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->warn("Would delete {$count} {$table} row(s) dated before {$cutoff}.");

                continue;
            }

            $deleted = DB::table($table)->where('day', '<', $cutoff)->delete();
            $this->info("Deleted {$deleted} {$table} row(s) older than {$days} days.");
        }
    }
}
