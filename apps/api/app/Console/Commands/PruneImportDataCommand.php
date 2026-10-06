<?php

namespace App\Console\Commands;

use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Daily import housekeeping: closes runs a killed process left "running",
 * and applies the retention of the import bookkeeping (docs/data-inventory.md):
 * after import.retention_days (365) the diff summary CSV of a run, a closed
 * review item and a lifted suppression are deleted. The run rows (counts,
 * no names) stay; open items and active suppressions are never pruned.
 */
class PruneImportDataCommand extends Command
{
    protected $signature = 'import:prune';

    protected $description = 'Delete import diff summaries, closed review items and lifted suppressions past their retention';

    public function handle(): int
    {
        // A run still "running" after six hours was killed (a run takes
        // minutes): closed as failed so the history and alerts stay honest.
        $interrupted = 0;

        ImportRun::query()
            ->where('status', ImportRunStatus::Running)
            ->where('started_at', '<', now()->subHours(6))
            ->each(function (ImportRun $run) use (&$interrupted): void {
                $run->fail('Run interrupted (still running after six hours; the process was killed or crashed).');
                $interrupted++;
            });

        $cutoff = now()->subDays(max(1, (int) config('import.retention_days')));
        $disk = Storage::disk((string) config('import.disk'));
        $diffs = 0;

        ImportRun::query()
            ->whereNotNull('diff_path')
            ->where('started_at', '<', $cutoff)
            ->chunkById(200, function ($runs) use ($disk, &$diffs): void {
                foreach ($runs as $run) {
                    $disk->delete((string) $run->diff_path);
                    $run->forceFill(['diff_path' => null])->save();
                    $diffs++;
                }
            });

        $items = ImportReviewItem::query()
            ->where('status', '!=', ImportReviewStatus::Open)
            ->where('resolved_at', '<', $cutoff)
            ->delete();

        $suppressions = ImportSuppression::query()
            ->whereNotNull('lifted_at')
            ->where('lifted_at', '<', $cutoff)
            ->delete();

        $this->info("Closed {$interrupted} interrupted runs. Deleted {$diffs} diff summaries, {$items} closed review items and {$suppressions} lifted suppressions.");

        return self::SUCCESS;
    }
}
