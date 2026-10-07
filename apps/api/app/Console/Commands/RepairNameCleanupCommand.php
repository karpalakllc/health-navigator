<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\Names\NameCleanupRepair;
use Illuminate\Console\Command;

/**
 * One-off: repairs what an earlier import:clean-names wrote with rules fixed
 * since (docs/data-import.md §12, NameCleanupRepair). Prints counts only —
 * never names; each repair is in the activity log (`name_cleanup`,
 * category `repair:…`).
 */
class RepairNameCleanupCommand extends Command
{
    protected $signature = 'import:repair-name-cleanup
        {--dry-run : Decide and count, change nothing (the default)}
        {--apply : Write the repairs, log them and refresh the open name items}';

    protected $description = 'Recompute the names an earlier import:clean-names changed (lower-cased name starts, Latin s → ѕ, partly cleaned uncertain names) from their original values';

    public function handle(NameCleanupRepair $repair): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose --dry-run or --apply.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');

        try {
            $run = $repair->run($apply);
        } catch (ImportAlreadyRunning $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $this->line(sprintf('Run #%d (%s): %s', $run->getKey(), $apply ? 'apply' : 'dry run', $run->status->value));

        if ($run->status === ImportRunStatus::Failed) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        $this->table(['count', 'value'], collect($run->counts ?? [])->map(fn ($value, $key) => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
