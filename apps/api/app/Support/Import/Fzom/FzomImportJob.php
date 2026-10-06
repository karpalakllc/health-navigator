<?php

namespace App\Support\Import\Fzom;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\SourceFetcher;

/**
 * One ФЗОМ run end to end: fetch both files (or use local ones), then import
 * them through ImportRunner. Used by `import:fzom` (and so the scheduler).
 */
final class FzomImportJob
{
    public function __construct(
        private readonly ImportRunner $runner,
        private readonly SourceFetcher $fetcher,
        private readonly FzomImporter $importer,
    ) {}

    /**
     * @param  array<string, string>|null  $localFiles  label => path; skips fetching (tests, manual runs)
     */
    public function run(bool $dryRun, ?User $by = null, ?array $localFiles = null, bool $force = false): ImportRun
    {
        return $this->runner->run(FzomImporter::SOURCE, $dryRun, $by, function (ImportContext $context) use ($dryRun, $localFiles, $force): array {
            if ($localFiles !== null) {
                $this->importer->import($context, $localFiles);

                return ['files' => array_map(fn (string $path): array => [
                    'local' => basename($path),
                    'sha256' => hash_file('sha256', $path) ?: null,
                    'bytes' => filesize($path) ?: 0,
                ], $localFiles)];
            }

            $previous = $this->previousMeta();
            $files = [];
            $meta = ['files' => []];

            foreach ((array) config('import.fzom.files') as $label => $url) {
                $result = $this->fetcher->fetch(FzomImporter::SOURCE, (string) $label, (string) $url, $previous[$label] ?? null);
                $files[(string) $label] = $result['local_path'];
                $meta['files'][$label] = array_diff_key($result, ['local_path' => true]);
            }

            $unchanged = collect($meta['files'])->every(fn (array $file): bool => $file['status'] === 304);

            if ($unchanged && ! $dryRun && ! $force) {
                return $meta + ['not_modified' => true];
            }

            $this->importer->import($context, $files);
            $this->fetcher->pruneSnapshots(FzomImporter::SOURCE);

            return $meta;
        });
    }

    /**
     * Conditional-GET state of the last successful apply.
     *
     * @return array<string, array{etag?: string|null, last_modified?: string|null, snapshot?: string|null}>
     */
    private function previousMeta(): array
    {
        $run = ImportRun::query()
            ->where('source', FzomImporter::SOURCE)
            ->where('dry_run', false)
            ->whereIn('status', [ImportRunStatus::Succeeded, ImportRunStatus::NotModified])
            ->latest('id')
            ->first();

        return (array) ($run?->source_meta['files'] ?? []);
    }
}
