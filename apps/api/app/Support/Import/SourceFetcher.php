<?php

namespace App\Support\Import;

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
 * - the body is written to the PRIVATE import disk (never public storage
 *   or git), and only the newest `snapshot_retention` snapshots per source
 *   are kept.
 */
class SourceFetcher
{
    /** @var array<string, float> host => when the last request finished */
    private array $lastRequestAt = [];

    /** @var array<string, list<string>> host => disallowed path prefixes for our agent */
    private array $robots = [];

    /**
     * @param  array{etag?: string|null, last_modified?: string|null, snapshot?: string|null}|null  $previous
     * @return array{status: int, snapshot: string, local_path: string, etag: string|null, last_modified: string|null, sha256: string, bytes: int, url: string}
     */
    public function fetch(string $source, string $label, string $url, ?array $previous, ?string $snapshotFolder = null): array
    {
        $this->assertAllowedByRobots($url);

        $disk = Storage::disk((string) config('import.disk'));
        $headers = [];
        $previousSnapshot = $previous['snapshot'] ?? null;
        $canReuse = $previousSnapshot !== null && $disk->exists($previousSnapshot);

        if ($canReuse && ! empty($previous['etag'])) {
            $headers['If-None-Match'] = (string) $previous['etag'];
        }

        if ($canReuse && ! empty($previous['last_modified'])) {
            $headers['If-Modified-Since'] = (string) $previous['last_modified'];
        }

        $response = $this->request($url, $headers);

        if ($response->status() === 304 && $canReuse) {
            /** @var string $previousSnapshot */
            return [
                'status' => 304,
                'snapshot' => $previousSnapshot,
                'local_path' => $disk->path($previousSnapshot),
                'etag' => $previous['etag'] ?? null,
                'last_modified' => $previous['last_modified'] ?? null,
                'sha256' => hash_file('sha256', $disk->path($previousSnapshot)) ?: '',
                'bytes' => (int) $disk->size($previousSnapshot),
                'url' => $url,
            ];
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('%s: HTTP %d from %s.', $label, $response->status(), parse_url($url, PHP_URL_HOST)));
        }

        $body = $response->body();

        if (strlen($body) > (int) config('import.max_download_bytes')) {
            throw new RuntimeException("{$label}: larger than import.max_download_bytes.");
        }

        $snapshot = $this->snapshotDirectory($source).'/'.($snapshotFolder ?? $this->newSnapshotFolder()).'/'.$label.'.'.(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin');
        $disk->put($snapshot, $body);

        return [
            'status' => $response->status(),
            'snapshot' => $snapshot,
            'local_path' => $disk->path($snapshot),
            'etag' => $response->header('ETag') ?: null,
            'last_modified' => $response->header('Last-Modified') ?: null,
            'sha256' => hash('sha256', $body),
            'bytes' => strlen($body),
            'url' => $url,
        ];
    }

    /**
     * Keeps the newest $keep snapshot folders of a source, deleting older ones.
     */
    public function pruneSnapshots(string $source, ?int $keep = null): void
    {
        $keep ??= max(1, (int) config('import.snapshot_retention'));
        $disk = Storage::disk((string) config('import.disk'));
        $directories = $disk->directories($this->snapshotDirectory($source));
        rsort($directories);

        foreach (array_slice($directories, $keep) as $directory) {
            $disk->deleteDirectory($directory);
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
    protected function request(string $url, array $headers): Response
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->throttle($host);

        try {
            return Http::withUserAgent($this->userAgent())
                ->withHeaders($headers)
                ->timeout((int) config('import.timeout_seconds'))
                ->get($url);
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
