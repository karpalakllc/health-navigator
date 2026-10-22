<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\Pharmacies\PharmacyImportJob;
use Illuminate\Console\Command;

/**
 * ФЗОМ's pharmacy contracts → hidden draft pharmacies, and the on-duty
 * schedule linked to them (docs/data-import.md §4a). Idempotent.
 */
class ImportPharmaciesCommand extends Command
{
    protected $signature = 'import:pharmacies
        {--dry-run : Run everything, report what would change, write nothing}
        {--file= : Use this local ФЗОМ primary-care XML (LekariLista_SitePzz.xml, or a kept snapshot) instead of downloading}
        {--force : Import even when the source answers 304 Not Modified}';

    protected $description = 'Import pharmacies from ФЗОМ\'s register of pharmacy contracts and link the on-duty schedule';

    public function handle(PharmacyImportJob $job): int
    {
        if (! (bool) config('import.pharmacies.enabled')) {
            $this->warn('The pharmacy import is switched off (IMPORT_PHARMACIES).');

            return self::SUCCESS;
        }

        $file = $this->option('file');
        $local = null;

        if (is_string($file) && $file !== '') {
            if (! is_readable($file)) {
                $this->error("Cannot read {$file}.");

                return self::FAILURE;
            }

            $local = ['pzz' => $file];
        }

        try {
            $run = $job->run((bool) $this->option('dry-run'), null, $local, (bool) $this->option('force'));
        } catch (ImportAlreadyRunning $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

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
