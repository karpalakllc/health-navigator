<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\Names\NameCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans the names of the profiles already imported (docs/data-import.md
 * §12). Prints counts only — never names; the private CSV of the run lists
 * every change.
 */
class CleanNamesCommand extends Command
{
    protected $signature = 'import:clean-names
        {--dry-run : Decide and count, change nothing (the default)}
        {--apply : Write the high-confidence fixes and queue the uncertain cases}';

    protected $description = 'Clean doctor and facility names (titles, roles, casing, script, legal forms, the town) and queue the uncertain cases and possible duplicates';

    public function handle(NameCleanup $cleanup): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose --dry-run or --apply.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');

        try {
            $run = $cleanup->run($apply);
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
        $this->line('Report (private): '.Storage::disk((string) config('import.disk'))->path((string) $run->diff_path));

        if ($apply) {
            $this->line('Run import:adjudicate next: the verification engine re-reads the cleaned names.');
        }

        return self::SUCCESS;
    }
}
