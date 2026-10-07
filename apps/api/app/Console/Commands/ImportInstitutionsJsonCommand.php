<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\Website\InstitutionsJsonImporter;
use App\Support\Verification\Engine\VerificationEngine;
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

    public function handle(ImportRunner $runner, InstitutionsJsonImporter $importer, VerificationEngine $engine): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        try {
            $run = $runner->run(InstitutionsJsonImporter::SOURCE, (bool) $this->option('dry-run'), null, function (ImportContext $context) use ($importer, $path): array {
                $importer->import($context, $path);

                return ['file' => basename(dirname($path)).'/'.basename($path), 'sha256' => hash_file('sha256', $path) ?: null];
            });
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

        // Website runs are not announced (ImportRunFinished), so the
        // verification engine is started here (docs/verification.md).
        if (! $run->dry_run && (bool) config('import.verification.after_import')) {
            $verification = $engine->runQuietly();
            $this->line($verification !== null
                ? sprintf('Verification run #%d: %s', $verification->getKey(), $verification->status->value)
                : 'Verification engine busy; the nightly run will re-evaluate.');
        }

        return self::SUCCESS;
    }
}
