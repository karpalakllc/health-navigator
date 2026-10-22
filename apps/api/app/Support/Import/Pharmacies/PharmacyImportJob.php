<?php

namespace App\Support\Import\Pharmacies;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\SourceFetcher;

/**
 * One import:pharmacies run end to end: fetch ФЗОМ's register (or use a
 * local file), keep only the minimised pharmacy snapshot, import it through
 * ImportRunner (dry run = one rolled-back transaction).
 */
final class PharmacyImportJob
{
    public function __construct(
        private readonly ImportRunner $runner,
        private readonly SourceFetcher $fetcher,
        private readonly PharmacyRegisterImporter $importer,
        private readonly FzomPharmacyReader $reader,
    ) {}

    /**
     * @param  array<string, string>|null  $localFiles  label => path; skips fetching
     */
    public function run(bool $dryRun, ?User $by = null, ?array $localFiles = null, bool $force = false): ImportRun
    {
        return $this->runner->run(PharmacyRegisterImporter::SOURCE, $dryRun, $by, function (ImportContext $context) use ($dryRun, $localFiles, $force): array {
            if ($localFiles !== null) {
                $this->importer->import($context, $localFiles);

                return ['complete' => PharmacyRegisterImporter::isComplete($localFiles), 'files' => array_map(fn (string $path): array => [
                    'local' => basename($path),
                    'sha256' => hash_file('sha256', $path) ?: null,
                    'bytes' => filesize($path) ?: 0,
                ], $localFiles)];
            }

            $previous = $this->previousMeta();
            $files = [];
            $meta = ['complete' => true, 'files' => []];
            $folder = $this->fetcher->newSnapshotFolder();

            try {
                foreach ((array) config('import.pharmacies.files') as $label => $url) {
                    $result = $this->fetcher->fetch(
                        PharmacyRegisterImporter::SOURCE,
                        (string) $label,
                        (string) $url,
                        $previous[$label] ?? null,
                        $folder,
                        fn (string $from, string $to) => $this->reader->minimise($from, $to, (string) $label),
                    );
                    $files[(string) $label] = $result['local_path'];
                    $meta['files'][$label] = array_diff_key($result, ['local_path' => true]);
                }

                $unchanged = collect($meta['files'])->every(fn (array $file): bool => $file['status'] === 304);

                if ($unchanged && ! $dryRun && ! $force) {
                    return $meta + ['not_modified' => true];
                }

                $this->importer->import($context, $files);
            } finally {
                if ($dryRun) {
                    $this->fetcher->deleteSnapshotFolder(PharmacyRegisterImporter::SOURCE, $folder);
                }

                $this->fetcher->pruneSnapshots(PharmacyRegisterImporter::SOURCE);
            }

            return $meta;
        });
    }

    /**
     * Conditional-GET state of the last successful apply.
     *
     * @return array<string, array{etag?: string|null, last_modified?: string|null, snapshot?: string|null, sha256?: string|null, bytes?: int|null, minimised?: bool}>
     */
    private function previousMeta(): array
    {
        $run = ImportRun::query()
            ->where('source', PharmacyRegisterImporter::SOURCE)
            ->where('dry_run', false)
            ->whereIn('status', [ImportRunStatus::Succeeded, ImportRunStatus::NotModified])
            ->latest('id')
            ->first();

        return (array) ($run?->source_meta['files'] ?? []);
    }
}
