<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Analytics events had no retention policy at all, while triage sessions did.
 *
 * That matters here more than on a typical product: `search.query` events store
 * the raw query string alongside a user id, and on this platform people search
 * symptoms. Keeping that indefinitely is health-adjacent personal data nobody
 * decided to retain.
 */
class PurgeOldAnalyticsEventsCommand extends Command
{
    protected $signature = 'analytics:purge-old-events
                            {--days=180 : Delete events older than this many days}
                            {--dry-run : Report counts without deleting}';

    protected $description = 'Delete analytics events older than the retention window (default 180 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $count = AnalyticsEvent::query()->where('occurred_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No analytics events older than {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$count} analytics event(s) recorded before {$cutoff->toDateString()}.");

            return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
