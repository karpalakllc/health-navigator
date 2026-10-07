<?php

namespace App\Support\Import\Pharmacies;

use App\Support\Import\RobotsTxt;
use App\Support\Import\SourcePolicy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Downloads ФЗОМ's monthly on-duty pharmacy schedules from the page that
 * links them (config import.on_duty_pharmacies).
 *
 * Same manners as the other import fetchers (SourcePolicy): our User-Agent
 * with a contact, robots.txt read first and honoured, https only and only
 * the page's own host (redirects included), a pause between requests, a
 * size cap, and a conditional GET (If-None-Match / If-Modified-Since) for a
 * file we still hold — a 304 reuses it. Raw files go to the private import
 * disk; only the newest `keep_files` are kept.
 */
final class OnDutyScheduleFetcher
{
    /** @var array<string, string> robots.txt per host */
    private array $robots = [];

    /**
     * The schedules the page links to, newest first as the page lists them.
     *
     * @return list<array{month: string, url: string, label: string}>
     */
    public function listed(): array
    {
        $pageUrl = (string) config('import.on_duty_pharmacies.page_url');
        $this->assertAllowed($pageUrl);
        $page = $this->request($pageUrl);

        if (! $page->successful()) {
            throw new RuntimeException("The on-duty pharmacy page answered HTTP {$page->status()}.");
        }

        return $this->links($page->body(), $pageUrl);
    }

    /**
     * @return list<array{month: string, url: string, label: string}>
     */
    public function links(string $html, string $pageUrl): array
    {
        $links = [];

        preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~isu', $html, $anchors, PREG_SET_ORDER);

        foreach ($anchors as $anchor) {
            $href = html_entity_decode($anchor[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/\.xlsx(\?.*)?$/i', $href) !== 1) {
                continue;
            }

            $url = $this->absolute($href, $pageUrl);

            if (! SourcePolicy::isFetchable($url, $this->allowedHosts())) {
                continue;
            }

            $label = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($anchor[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            $month = OnDutyScheduleParser::monthFromLink($label, $url);

            // The first link for a month wins (the page lists the current one first).
            if ($month !== null && ! isset($links[$month])) {
                $links[$month] = ['month' => $month, 'url' => $url, 'label' => mb_substr($label, 0, 120)];
            }
        }

        return array_values($links);
    }

    /**
     * Downloads one schedule. `$previous` is what the last run of that month
     * stored (url, etag, last_modified, path): with the file still on disk
     * the request is conditional, and a 304 answers `not_modified`.
     *
     * @param  array{month: string, url: string, label: string}  $link
     * @param  array<string, mixed>|null  $previous
     * @return array{not_modified: bool, path: string, absolute_path: string, sha256: string, etag: string|null, last_modified: string|null, bytes: int}
     */
    public function download(array $link, ?array $previous, bool $force = false): array
    {
        $this->assertAllowed($link['url']);
        $disk = Storage::disk((string) config('import.disk'));
        $headers = [];
        $held = is_array($previous)
            && ($previous['url'] ?? null) === $link['url']
            && is_string($previous['path'] ?? null)
            && $disk->exists($previous['path']);

        if ($held && ! $force) {
            if (is_string($previous['etag'] ?? null)) {
                $headers['If-None-Match'] = $previous['etag'];
            }

            if (is_string($previous['last_modified'] ?? null)) {
                $headers['If-Modified-Since'] = $previous['last_modified'];
            }
        }

        $this->pause();
        $response = $this->request($link['url'], $headers);

        if ($response->status() === 304 && $held) {
            $path = (string) $previous['path'];

            return [
                'not_modified' => true,
                'path' => $path,
                'absolute_path' => $disk->path($path),
                'sha256' => (string) ($previous['sha256'] ?? ''),
                'etag' => is_string($previous['etag'] ?? null) ? $previous['etag'] : null,
                'last_modified' => is_string($previous['last_modified'] ?? null) ? $previous['last_modified'] : null,
                'bytes' => (int) ($previous['bytes'] ?? 0),
            ];
        }

        if (! $response->successful()) {
            throw new RuntimeException("The schedule for {$link['month']} answered HTTP {$response->status()}.");
        }

        $body = $response->body();

        if (strlen($body) > $this->maxBytes()) {
            throw new RuntimeException("The schedule for {$link['month']} is larger than allowed.");
        }

        // An .xlsx is a zip archive.
        if (! str_starts_with($body, "PK\x03\x04")) {
            throw new RuntimeException("The schedule for {$link['month']} is not an .xlsx file.");
        }

        $sha = hash('sha256', $body);
        $path = trim((string) config('import.on_duty_pharmacies.directory'), '/').'/'.$link['month'].'-'.substr($sha, 0, 12).'.xlsx';
        $disk->put($path, $body);
        $this->prune();

        return [
            'not_modified' => $held && ! $force && ($previous['sha256'] ?? null) === $sha,
            'path' => $path,
            'absolute_path' => $disk->path($path),
            'sha256' => $sha,
            'etag' => $response->header('ETag') ?: null,
            'last_modified' => $response->header('Last-Modified') ?: null,
            'bytes' => strlen($body),
        ];
    }

    /** Keeps the newest keep_files raw files; the rest are deleted. */
    private function prune(): void
    {
        $disk = Storage::disk((string) config('import.disk'));
        $directory = trim((string) config('import.on_duty_pharmacies.directory'), '/');
        $files = $disk->files($directory);
        usort($files, fn (string $a, string $b): int => $disk->lastModified($b) <=> $disk->lastModified($a) ?: strcmp($b, $a));

        foreach (array_slice($files, max(1, (int) config('import.on_duty_pharmacies.keep_files', 3))) as $old) {
            $disk->delete($old);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        return array_values(array_unique(array_filter([
            strtolower((string) parse_url((string) config('import.on_duty_pharmacies.page_url'), PHP_URL_HOST)),
            ...array_map('strtolower', (array) config('import.on_duty_pharmacies.extra_hosts', [])),
        ])));
    }

    private function assertAllowed(string $url): void
    {
        SourcePolicy::assertFetchable($url, $this->allowedHosts());

        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        if (! array_key_exists($host, $this->robots)) {
            $this->robots[$host] = SourcePolicy::robotsBody($this->request("https://{$host}/robots.txt"), $host);
        }

        if (! RobotsTxt::allows($this->robots[$host], SourcePolicy::agentToken(), $path)) {
            throw new RuntimeException("robots.txt of {$host} does not allow {$path}.");
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $url, array $headers = []): Response
    {
        $max = $this->maxBytes();

        return Http::withHeaders($headers + ['User-Agent' => SourcePolicy::userAgent()])
            ->withOptions(SourcePolicy::requestOptions($this->allowedHosts()) + [
                'progress' => function (int $total, int $downloaded) use ($max): void {
                    if ($total > $max || $downloaded > $max) {
                        throw new RuntimeException('The schedule file is larger than allowed.');
                    }
                },
            ])
            ->timeout((int) config('import.timeout_seconds', 60))
            ->get($url);
    }

    private function maxBytes(): int
    {
        return (int) config('import.on_duty_pharmacies.max_bytes', 5 * 1024 * 1024);
    }

    private function pause(): void
    {
        $delay = (int) config('import.on_duty_pharmacies.request_delay_ms', 2000);

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    private function absolute(string $href, string $pageUrl): string
    {
        $href = str_replace(' ', '%20', trim($href));

        if (preg_match('~^https?://~i', $href) === 1) {
            return $href;
        }

        $scheme = (string) parse_url($pageUrl, PHP_URL_SCHEME);
        $host = (string) parse_url($pageUrl, PHP_URL_HOST);

        return str_starts_with($href, '/')
            ? "{$scheme}://{$host}{$href}"
            : rtrim(dirname($pageUrl), '/').'/'.$href;
    }
}
