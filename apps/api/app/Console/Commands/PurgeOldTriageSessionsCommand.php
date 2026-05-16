<?php

namespace App\Console\Commands;

use App\Models\TriageSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeOldTriageSessionsCommand extends Command
{
    protected $signature = 'triage:purge-old-sessions
                            {--days=90 : Delete sessions older than this many days}
                            {--dry-run : Report counts without deleting}';

    protected $description = 'Delete triage sessions and answers older than the retention window (default 90 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = TriageSession::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No triage sessions older than {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Would delete {$count} triage session(s) created before {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->orderBy('created_at')->chunk(200, function ($sessions) use (&$deleted): void {
            foreach ($sessions as $session) {
                $session->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} triage session(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
