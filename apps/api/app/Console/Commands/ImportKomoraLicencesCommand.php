<?php

namespace App\Console\Commands;

use App\Support\Import\Contracts\DoctorLicenceSink;
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

    public function handle(KomoraLicenceListParser $parser, KomoraLicenceFetcher $fetcher, LicenceCandidateSource $candidates): int
    {
        $dryRun = (bool) $this->option('dry-run');
        /** @var list<string> $localFiles */
        $localFiles = array_values(array_filter((array) $this->option('file'), 'is_string'));

        try {
            if ($localFiles !== []) {
                $files = [];

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
            } else {
                $fetched = $fetcher->fetch((bool) $this->option('force'));

                if (! $fetched['changed']) {
                    $this->info('The licence list has not changed since the last download; nothing to do (use --force to process it anyway).');

                    return self::SUCCESS;
                }

                $files = $fetched['files'];
                $listDate = $fetched['list_date'] ?? CarbonImmutable::today();

                if ($fetched['list_date'] === null) {
                    $this->warn('The list page states no date; using today as the list date.');
                }

                $complete = true;
            }

            $parsed = new LicenceParseResult;

            foreach ($files as $file) {
                $parsed = $parsed->merge($parser->parsePdf($file['path'], $file['label']));
            }

            if ($parsed->rows === []) {
                $this->error('No licence rows could be read from the list files; nothing was changed.');

                return self::FAILURE;
            }

            $sink = app()->bound(DoctorLicenceSink::class) ? app(DoctorLicenceSink::class) : null;

            if ($sink === null && ! $dryRun) {
                $this->error('No DoctorLicenceSink is bound (the import core provides it). Run with --dry-run.');

                return self::FAILURE;
            }

            $counts = (new KomoraLicenceImporter($candidates, $sink))->import($parsed, $listDate, $dryRun, $complete);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Licence import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line(($dryRun ? 'Dry run — nothing written. ' : '').'List of '.$listDate->format('d.m.Y').', '.count($files).' file(s).');
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
