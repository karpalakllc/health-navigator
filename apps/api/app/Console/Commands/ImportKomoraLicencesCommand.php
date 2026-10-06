<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\ImportRunAnnouncer;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\KomoraLicenceFetcher;
use App\Support\Licences\KomoraLicenceImporter;
use App\Support\Licences\KomoraLicenceListParser;
use App\Support\Licences\LicenceParseResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The Лекарска комора „Листа на доктори со важечки лиценци“: download (or
 * read local files), parse, match to imported doctor profiles and hand the
 * result to the import core. Prints counts only — never names.
 */
class ImportKomoraLicencesCommand extends Command
{
    protected $signature = 'import:komora-licences
                            {--dry-run : Parse and match, print the counts, write nothing}
                            {--file=* : Read these local list PDFs instead of downloading (a partial list never marks licences missing)}
                            {--list-date= : Date of the list (dd.mm.yyyy) for --file runs; read from the file names if left out}
                            {--complete : With --file: the files are the whole list, so a licence not in them is missing}
                            {--force : Process the list even when no file changed since the last download}';

    protected $description = 'Import the Лекарска комора licence list: attach licence numbers and expiry to imported doctor profiles';

    public function handle(KomoraLicenceListParser $parser, KomoraLicenceFetcher $fetcher, LicenceCandidateSource $candidates, ImportRunAnnouncer $announcer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        /** @var list<string> $localFiles */
        $localFiles = array_values(array_filter((array) $this->option('file'), 'is_string'));
        $files = [];
        $listDate = null;
        $complete = true;

        if ($localFiles !== []) {
            foreach ($localFiles as $path) {
                if (! is_file($path)) {
                    $this->error("No such file: {$path}");

                    return self::FAILURE;
                }

                $files[] = ['path' => $path, 'label' => pathinfo($path, PATHINFO_FILENAME)];
            }

            $listDate = $this->localListDate($localFiles);

            if ($listDate === null) {
                $this->error('Give the list date with --list-date=dd.mm.yyyy.');

                return self::FAILURE;
            }

            $complete = (bool) $this->option('complete');
        }

        // One run at a time (shared with the scheduler).
        try {
            $lock = ImportAlreadyRunning::lock(KomoraLicenceImporter::SOURCE);
        } catch (ImportAlreadyRunning $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        try {
            return $this->runLocked($parser, $fetcher, $candidates, $announcer, $dryRun, $localFiles, $files, $listDate, $complete);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $localFiles
     * @param  list<array{path: string, label: string}>  $files
     */
    private function runLocked(
        KomoraLicenceListParser $parser,
        KomoraLicenceFetcher $fetcher,
        LicenceCandidateSource $candidates,
        ImportRunAnnouncer $announcer,
        bool $dryRun,
        array $localFiles,
        array $files,
        ?CarbonImmutable $listDate,
        bool $complete,
    ): int {
        $hashes = null;

        // One import_runs row per run, like every other source: staff see it
        // under Data import → Import runs, review items link back to it, and
        // a real run that ends is announced to the alerts.
        $run = ImportRun::start(KomoraLicenceImporter::SOURCE, $dryRun);

        try {
            if ($localFiles === []) {
                $fetched = $fetcher->fetch((bool) $this->option('force'));
                $hashes = collect($fetched['files'])->mapWithKeys(fn (array $file): array => [$file['url'] => $file['sha256']])->sortKeys()->all();

                // Unchanged = the same files as the last list that was
                // successfully APPLIED: a dry run or a failed apply does not
                // use a new list up.
                if (! $this->option('force') && $hashes === $this->lastAppliedHashes()) {
                    $run->finish([], ImportRunStatus::NotModified);
                    $this->info('The licence list has not changed since the last download; nothing to do (use --force to process it anyway).');

                    return self::SUCCESS;
                }

                $files = $fetched['files'];
                $listDate = $fetched['list_date'] ?? CarbonImmutable::today();

                if ($fetched['list_date'] === null) {
                    $this->warn('The list page states no date; using today as the list date.');
                }
            }

            $parsed = new LicenceParseResult;

            foreach ($files as $file) {
                $parsed = $parsed->merge($parser->parsePdf($file['path'], $file['label']));
            }

            if ($parsed->rows === []) {
                return $this->failRun($run, $announcer, 'No licence rows could be read from the list files; nothing was changed.');
            }

            $sink = app()->bound(DoctorLicenceSink::class) ? app(DoctorLicenceSink::class) : null;

            if ($sink === null && ! $dryRun) {
                return $this->failRun($run, $announcer, 'No DoctorLicenceSink is bound (the import core provides it). Run with --dry-run.');
            }

            /** @var CarbonImmutable $listDate */
            $counts = (new KomoraLicenceImporter($candidates, $sink))->import($parsed, $listDate, $dryRun, $complete, $dryRun ? null : (int) $run->getKey());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failRun($run, $announcer, 'Licence import failed: '.$exception->getMessage(), $exception);
        }

        $run->forceFill(['source_meta' => [
            'list_date' => $listDate->toDateString(),
            'files' => array_map(fn (array $file): string => $file['label'], $files),
            'complete_list' => $complete,
            'file_hashes' => $hashes ?? null,
        ]]);
        $run->finish($counts);
        $announcer->announce($run);

        $this->line(($dryRun ? 'Dry run — nothing written. ' : '').'List of '.$listDate->format('d.m.Y').', '.count($files).' file(s). Run #'.$run->getKey().'.');
        $this->table(['', 'count'], array_map(
            fn (string $key, int $count): array => [str_replace('_', ' ', $key), $count],
            array_keys($counts),
            array_values($counts),
        ));

        foreach (array_slice($parsed->failures, 0, 20) as $failure) {
            $this->warn("Unreadable: {$failure['reference']} ({$failure['reason']})");
        }

        return self::SUCCESS;
    }

    private function failRun(ImportRun $run, ImportRunAnnouncer $announcer, string $message, ?Throwable $exception = null): int
    {
        $run->fail($exception ?? $message);
        $announcer->announce($run);
        $this->error($message);

        return self::FAILURE;
    }

    /**
     * url => sha256 of the files of the last successful apply of a
     * downloaded list (null: none yet).
     *
     * @return array<string, string>|null
     */
    private function lastAppliedHashes(): ?array
    {
        $meta = ImportRun::query()
            ->where('source', KomoraLicenceImporter::SOURCE)
            ->where('dry_run', false)
            ->where('status', ImportRunStatus::Succeeded)
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (ImportRun $run): bool => is_array($run->source_meta['file_hashes'] ?? null))
            ?->source_meta;

        if ($meta === null) {
            return null;
        }

        $hashes = (array) $meta['file_hashes'];
        ksort($hashes);

        return $hashes;
    }

    /**
     * @param  list<string>  $paths
     */
    private function localListDate(array $paths): ?CarbonImmutable
    {
        $given = $this->option('list-date');
        $candidates = is_string($given) && $given !== '' ? [$given] : array_map('basename', $paths);

        foreach ($candidates as $text) {
            if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $text, $match) === 1
                && checkdate((int) $match[2], (int) $match[1], (int) $match[3])) {
                return CarbonImmutable::create((int) $match[3], (int) $match[2], (int) $match[1])->startOfDay();
            }
        }

        return null;
    }
}
