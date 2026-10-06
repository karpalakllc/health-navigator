<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\Website\InstitutionsJsonImporter;
use Illuminate\Console\Command;

/**
 * Institutions and their doctors compiled from official websites
 * (institutions.json + logos/ + covers/) → hidden drafts (docs/data-import.md).
 */
class ImportInstitutionsJsonCommand extends Command
{
    protected $signature = 'import:institutions-json
        {path : institutions.json (its logos/ and covers/ folders sit next to it)}
        {--dry-run : Run everything, report what would change, write nothing}';

    protected $description = 'Import institutions and listed doctors from a website research dataset (JSON)';

    public function handle(ImportRunner $runner, InstitutionsJsonImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $run = $runner->run(InstitutionsJsonImporter::SOURCE, (bool) $this->option('dry-run'), null, function (ImportContext $context) use ($importer, $path): array {
            $importer->import($context, $path);

            return ['file' => basename(dirname($path)).'/'.basename($path), 'sha256' => hash_file('sha256', $path) ?: null];
        });

        $this->line(sprintf('Run #%d (%s): %s', $run->getKey(), $run->dry_run ? 'dry run' : 'apply', $run->status->value));

        if ($run->status === ImportRunStatus::Failed) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        $this->table(['count', 'value'], collect($run->counts ?? [])->map(fn ($value, $key) => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
