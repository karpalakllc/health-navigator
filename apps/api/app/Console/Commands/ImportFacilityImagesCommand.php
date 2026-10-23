<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\Website\FacilityImagesImporter;
use Illuminate\Console\Command;

/**
 * Logos and cover photos for existing facilities, by id (images.json +
 * its image files); docs/data-import.md §6b.
 */
class ImportFacilityImagesCommand extends Command
{
    protected $signature = 'import:facility-images
        {path : images.json (image paths in it are relative to its folder)}
        {--dry-run : Run everything, report what would change, store nothing}';

    protected $description = 'Attach website logos and cover photos to existing facilities and pharmacies by id (images.json)';

    public function handle(ImportRunner $runner, FacilityImagesImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        try {
            $run = $runner->run(FacilityImagesImporter::SOURCE, (bool) $this->option('dry-run'), null, function (ImportContext $context) use ($importer, $path): array {
                $importer->import($context, $path);

                return ['file' => basename(dirname($path)).'/'.basename($path), 'sha256' => hash_file('sha256', $path) ?: null, 'kind' => 'facility-images'];
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

        if ($importer->report() !== []) {
            $this->line('Not imported (skipped or needs review):');
            $this->table(['facility_id', 'name', 'outcome'], $importer->report());
        }

        return self::SUCCESS;
    }
}
