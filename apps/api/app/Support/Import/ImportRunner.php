<?php

namespace App\Support\Import;

use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\TaxonomyCache;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs one import with the guarantees every importer shares:
 *
 * - an import_runs row with counts, source metadata and a diff summary
 *   (CSV on the private import disk, downloadable by staff);
 * - a dry run executes the exact same code inside one transaction that is
 *   rolled back, so its counts and diff are what an apply would do;
 * - an apply commits per batch (the importer opens a transaction per
 *   batch), so a failure keeps finished batches and the run is re-runnable:
 *   every importer is idempotent;
 * - no search-index traffic and no activity-log rows per imported record
 *   (drafts are not searchable anyway; the run, its diff and field
 *   provenance are the audit trail). Publishing later goes through the
 *   normal model events, which index and log as usual;
 * - a finished or failed apply is announced as ImportRunFinished
 *   (ImportRunAnnouncer), which the data-ops alerts listen to;
 * - one run per source at a time (a cache lock): a second one stops at once
 *   with ImportAlreadyRunning.
 */
final class ImportRunner
{
    public function __construct(private readonly ImportRunAnnouncer $announcer) {}

    /**
     * @param  Closure(ImportContext): (array<string, mixed>|null)  $work  returns source metadata to store on the run
     */
    /**
     * @throws ImportAlreadyRunning when another run of the source is in progress
     */
    public function run(string $source, bool $dryRun, ?User $by, Closure $work): ImportRun
    {
        $lock = ImportAlreadyRunning::lock($source);

        try {
            return $this->runLocked($source, $dryRun, $by, $work);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Closure(ImportContext): (array<string, mixed>|null)  $work
     */
    private function runLocked(string $source, bool $dryRun, ?User $by, Closure $work): ImportRun
    {
        $run = ImportRun::start($source, $dryRun, $by);
        $context = new ImportContext($run, $source, $dryRun);

        $execute = fn () => Doctor::withoutSyncingToSearch(
            fn () => Facility::withoutSyncingToSearch(
                fn () => activity()->withoutLogging(fn () => $work($context)),
            ),
        );

        try {
            if ($dryRun) {
                DB::beginTransaction();

                try {
                    $meta = $execute();
                } finally {
                    DB::rollBack();
                }
            } else {
                $meta = $execute();
            }
        } catch (Throwable $exception) {
            $run->forceFill(['counts' => $context->counts()]);
            $this->writeDiff($run, $context);
            $run->fail($exception);
            report($exception);
            $this->announcer->announce($run);

            return $run;
        }

        if (is_array($meta) && ($meta['not_modified'] ?? false) === true) {
            $run->forceFill(['source_meta' => $meta])->save();
            $run->finish($context->counts(), ImportRunStatus::NotModified);

            return $run;
        }

        if (is_array($meta)) {
            $run->forceFill(['source_meta' => $meta]);
        }

        $this->writeDiff($run, $context);
        $run->finish($context->counts());

        if (! $dryRun) {
            TaxonomyCache::flush(TaxonomyCache::SPECIALTIES, TaxonomyCache::LANGUAGES, TaxonomyCache::HOME_HIGHLIGHTS);
        }

        // Alerts (failed or unusually large runs); dry and not-modified runs are not announced.
        $this->announcer->announce($run);

        return $run;
    }

    private function writeDiff(ImportRun $run, ImportContext $context): void
    {
        $diff = $context->diff();

        if ($diff === []) {
            return;
        }

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return;
        }

        // Excel opens a BOM-prefixed CSV as UTF-8 (Cyrillic names).
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['entity', 'action', 'record_id', 'label', 'field', 'old', 'new', 'note'], ',', '"', '');

        foreach ($diff as $line) {
            fputcsv($handle, array_map(self::neutraliseFormula(...), array_values($line)), ',', '"', '');
        }

        rewind($handle);
        $path = trim((string) config('import.directory'), '/').'/runs/'.$run->getKey().'/diff.csv';
        Storage::disk((string) config('import.disk'))->put($path, (string) stream_get_contents($handle));
        fclose($handle);

        $run->forceFill(['diff_path' => $path]);
    }

    /**
     * A cell starting with = + - @ is a formula to a spreadsheet; source data
     * is untrusted, so such cells get a leading apostrophe.
     */
    private static function neutraliseFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
