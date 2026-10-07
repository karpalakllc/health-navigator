<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the anonymous „Дали ви помогна?“ and step counters past their
 * retention window (config feedback.retention_days, docs/urgent-care.md).
 */
class PurgeOldFeedbackCommand extends Command
{
    protected $signature = 'feedback:purge-old
                            {--days= : Delete counters older than this many days (default: config feedback.retention_days, 730)}
                            {--dry-run : Report counts without deleting}';

    protected $description = 'Delete anonymous feedback and step counters past their retention window';

    private const TABLES = ['feedback_counters', 'feedback_reason_counters', 'funnel_step_counters'];

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('feedback.retention_days', 730)));
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        foreach (self::TABLES as $table) {
            $query = DB::table($table)->where('day', '<', $cutoff);

            if ($this->option('dry-run')) {
                $this->line(sprintf('Would delete %d row(s) from %s before %s.', $query->count(), $table, $cutoff));

                continue;
            }

            $this->line(sprintf('Deleted %d row(s) from %s before %s.', $query->delete(), $table, $cutoff));
        }

        return self::SUCCESS;
    }
}
