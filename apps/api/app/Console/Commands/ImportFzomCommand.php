<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Console\Command;

/**
 * ФЗОМ „Шифрарник на лекари“ → hidden draft doctors and facilities
 * (docs/data-import.md). Facilities first, then doctors; idempotent.
 */
class ImportFzomCommand extends Command
{
    protected $signature = 'import:fzom
        {--dry-run : Run everything, report what would change, write nothing}
        {--pzz= : Use this local primary-care XML instead of downloading}
        {--spec= : Use this local specialist XML instead of downloading}
        {--force : Import even when the source answers 304 Not Modified}';

    protected $description = 'Import doctors and facilities from the ФЗОМ doctor register (XML)';

    public function handle(FzomImportJob $job): int
    {
        $local = null;

        if ($this->option('pzz') !== null || $this->option('spec') !== null) {
            $local = array_filter([
                'pzz' => $this->option('pzz'),
                'spec' => $this->option('spec'),
            ], fn ($path): bool => is_string($path) && $path !== '');

            foreach ($local as $path) {
                if (! is_readable($path)) {
                    $this->error("Cannot read {$path}.");

                    return self::FAILURE;
                }
            }
        }

        $run = $job->run((bool) $this->option('dry-run'), null, $local, (bool) $this->option('force'));

        return $this->report($run);
    }

    private function report(ImportRun $run): int
    {
        $this->line(sprintf('Run #%d (%s): %s', $run->getKey(), $run->dry_run ? 'dry run' : 'apply', $run->status->value));

        if ($run->status === ImportRunStatus::Failed) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        $this->table(['count', 'value'], collect($run->counts ?? [])->map(fn ($value, $key) => [$key, $value])->values()->all());

        if ($run->diff_path !== null) {
            $this->line('Diff summary (private disk): '.$run->diff_path);
        }

        return self::SUCCESS;
    }
}
