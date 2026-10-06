<?php

namespace App\Support\Import;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downloads public source files politely:
 *
 * - robots.txt of the host is read first and honoured (a missing file = allowed);
 * - every request carries our User-Agent with a contact address;
 * - requests to one host are spaced by `import.request_delay_seconds`;
 * - conditional GET (If-None-Match / If-Modified-Since) from the previous
 *   run's metadata: a 304 reuses the stored snapshot;
 * - the body is streamed to a temporary file (size-capped), and only a
 *   minimised copy — the fields we import, nothing excluded — is written to
 *   the PRIVATE import disk (never public storage or git); the original's
 *   sha256 and size go in the run metadata. Only the newest
 *   `snapshot_retention` snapshots per source are kept.
 */
class SourceFetcher
{
    /** Present in every snapshot folder written after minimisation. */
    public const MINIMISED_MARKER = '.minimised';

    /** @var array<string, float> host => when the last request finished */
    private array $lastRequestAt = [];

    /** @var array<string, list<string>> host => disallowed path prefixes for our agent */
    private array $robots = [];

    /**
     * @param  array{etag?: string|null, last_modified?: string|null, snapshot?: string|null, sha256?: string|null, bytes?: int|null, minimised?: bool}|null  $previous
     * @param  (Closure(string, string): void)|null  $minimise  writes the copy we keep (from raw path, to path); excluded data never reaches the disk
     * @return array{status: int, snapshot: string, local_path: string, etag: string|null, last_modified: string|null, sha256: string, bytes: int, url: string, minimised: bool}
     */
    public function fetch(string $source, string $label, string $url, ?array $previous, ?string $snapshotFolder = null, ?Closure $minimise = null): array
    {
        $this->assertAllowedByRobots($url);

        $disk = Storage::disk((string) config('import.disk'));
        $headers = [];
        $previousSnapshot = $previous['snapshot'] ?? null;
        $canReuse = $previousSnapshot !== null && $disk->exists($previousSnapshot)
            && ($minimise === null || ($previous['minimised'] ?? false) === true);

        if ($canReuse && ! empty($previous['etag'])) {
            $headers['If-None-Match'] = (string) $previous['etag'];
        }

        if ($canReuse && ! empty($previous['last_modified'])) {
            $headers['If-Modified-Since'] = (string) $previous['last_modified'];
        }

        // The raw body goes to a temporary file outside the import disk and
        // is deleted below, whatever happens.
        $raw = (string) tempnam(sys_get_temp_dir(), 'import-raw-');

        try {
            $response = $this->request($url, $headers, $raw);

            if ($response->status() === 304 && $canReuse) {
                /** @var string $previousSnapshot */
                return [
                    'status' => 304,
                    'snapshot' => $previousSnapshot,
                    'local_path' => $disk->path($previousSnapshot),
                    'etag' => $previous['etag'] ?? null,
                    'last_modified' => $previous['last_modified'] ?? null,
                    'sha256' => (string) ($previous['sha256'] ?? (hash_file('sha256', $disk->path($previousSnapshot)) ?: '')),
                    'bytes' => (int) ($previous['bytes'] ?? $disk->size($previousSnapshot)),
                    'url' => $url,
                    'minimised' => (bool) ($previous['minimised'] ?? false),
                ];
            }

            if (! $response->successful()) {
                throw new RuntimeException(sprintf('%s: HTTP %d from %s.', $label, $response->status(), parse_url($url, PHP_URL_HOST)));
            }

            clearstatcache(true, $raw);
            $bytes = (int) filesize($raw);

            if ($bytes > (int) config('import.max_download_bytes')) {
                throw new RuntimeException("{$label}: larger than import.max_download_bytes.");
            }

            $folder = $this->snapshotDirectory($source).'/'.($snapshotFolder ?? $this->newSnapshotFolder());
            $snapshot = $folder.'/'.$label.'.'.(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin');
            $sha = (string) hash_file('sha256', $raw);

            if ($minimise !== null) {
                $kept = (string) tempnam(sys_get_temp_dir(), 'import-kept-');

                try {
                    $minimise($raw, $kept);
                    $this->store($disk, $snapshot, $kept);
                    $disk->put($folder.'/'.self::MINIMISED_MARKER, '');
                } finally {
                    @unlink($kept);
                }
            } else {
                $this->store($disk, $snapshot, $raw);
            }

            return [
                'status' => $response->status(),
                'snapshot' => $snapshot,
                'local_path' => $disk->path($snapshot),
                'etag' => $response->header('ETag') ?: null,
                'last_modified' => $response->header('Last-Modified') ?: null,
                'sha256' => $sha,
                'bytes' => $bytes,
                'url' => $url,
                'minimised' => $minimise !== null,
            ];
        } finally {
            @unlink($raw);
        }
    }

    /**
     * Keeps the newest $keep snapshot folders of a source, deleting older
     * ones (a 304 whose snapshot is gone simply downloads again). A folder
     * without the minimised marker predates minimisation and may hold
     * excluded fields: always deleted.
     */
    public function pruneSnapshots(string $source, ?int $keep = null): void
    {
        $keep ??= max(1, (int) config('import.snapshot_retention'));
        $disk = Storage::disk((string) config('import.disk'));
        $directories = [];

        foreach ($disk->directories($this->snapshotDirectory($source)) as $directory) {
            if ($disk->exists($directory.'/'.self::MINIMISED_MARKER)) {
                $directories[] = $directory;
            } else {
                $disk->deleteDirectory($directory);
            }
        }

        rsort($directories);

        foreach (array_slice($directories, $keep) as $directory) {
            $disk->deleteDirectory($directory);
        }
    }

    public function deleteSnapshotFolder(string $source, string $folder): void
    {
        Storage::disk((string) config('import.disk'))->deleteDirectory($this->snapshotDirectory($source).'/'.$folder);
    }

    private function store(Filesystem $disk, string $path, string $localFile): void
    {
        $stream = fopen($localFile, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Cannot read {$localFile}.");
        }

        try {
            $disk->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * One folder per run, sortable by time: retention keeps the newest.
     */
    public function newSnapshotFolder(): string
    {
        return now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
    }

    public function snapshotDirectory(string $source): string
    {
        return trim((string) config('import.directory'), '/').'/snapshots/'.$source;
    }

    public function userAgent(): string
    {
        return sprintf('%s (+mailto:%s)', config('import.user_agent'), config('import.contact'));
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function request(string $url, array $headers, ?string $sink = null): Response
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->throttle($host);
        $max = (int) config('import.max_download_bytes');

        $request = Http::withUserAgent($this->userAgent())
            ->withHeaders($headers)
            ->timeout((int) config('import.timeout_seconds'));

        if ($sink !== null) {
            // Streamed to disk; aborted as soon as the size passes the cap.
            $request = $request->sink($sink)->withOptions(['progress' => function (int $total, int $downloaded) use ($max): void {
                if ($total > $max || $downloaded > $max) {
                    throw new RuntimeException('Download larger than import.max_download_bytes.');
                }
            }]);
        }

        try {
            return $request->get($url);
        } finally {
            $this->lastRequestAt[$host] = microtime(true);
        }
    }

    private function throttle(string $host): void
    {
        $delay = (float) config('import.request_delay_seconds');

        if ($delay <= 0 || ! isset($this->lastRequestAt[$host])) {
            return;
        }

        $wait = $this->lastRequestAt[$host] + $delay - microtime(true);

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
    }

    private function assertAllowedByRobots(string $url): void
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/');

        if (! array_key_exists($host, $this->robots)) {
            $robotsUrl = ($parts['scheme'] ?? 'https').'://'.$host.'/robots.txt';
            $response = $this->request($robotsUrl, []);
            $this->robots[$host] = $response->successful() ? RobotsTxt::disallowedFor($response->body(), (string) config('import.user_agent')) : [];
        }

        foreach ($this->robots[$host] as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                throw new RuntimeException("robots.txt of {$host} disallows {$path}; not fetching.");
            }
        }
    }
}
