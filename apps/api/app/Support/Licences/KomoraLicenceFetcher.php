<?php

namespace App\Support\Licences;

use App\Models\KomoraLicenceDownload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downloads the Лекарска комора licence list: the page, then each PDF it
 * links to (config licences.komora).
 *
 * Polite by construction: robots.txt is read first and honoured, every
 * request carries our User-Agent with a contact, requests are spaced out, and
 * a file we already hold is asked for conditionally (If-None-Match /
 * If-Modified-Since) — a 304 reuses the stored copy. Raw files go to the
 * private disk under imports/komora/<batch>/ and only the last few batches
 * are kept.
 */
final class KomoraLicenceFetcher
{
    /** @var array<string, string> robots.txt per host, read once per fetcher */
    private array $robots = [];

    /**
     * @return array{batch: string, changed: bool, list_date: CarbonImmutable|null, files: list<array{path: string, label: string}>}
     */
    public function fetch(bool $force = false): array
    {
        $listUrl = (string) config('licences.komora.list_url');
        $this->assertAllowed($listUrl);

        $page = $this->request($listUrl);

        if (! $page->successful()) {
            throw new RuntimeException("The licence list page answered HTTP {$page->status()}.");
        }

        $html = $page->body();
        $links = $this->links($html, $listUrl);

        if ($links === []) {
            throw new RuntimeException('The licence list page links to no list files; the page layout may have changed.');
        }

        foreach ($links as $link) {
            $this->assertAllowed($link['url']);
        }

        $listDate = $this->listDate($html, $links);
        $batch = now()->format('Ymd-His').'-'.Str::lower(Str::random(4));
        $disk = Storage::disk((string) config('licences.komora.disk', 'local'));
        $directory = trim((string) config('licences.komora.directory', 'imports/komora'), '/');
        $changed = false;
        $files = [];

        foreach ($links as $index => $link) {
            $this->pause();

            $previous = KomoraLicenceDownload::query()
                ->where('url', $link['url'])
                ->whereNotNull('storage_path')
                ->latest('id')
                ->first();

            if ($previous !== null && ! $disk->exists((string) $previous->storage_path)) {
                $previous = null;
            }

            $headers = [];

            if ($previous !== null && ! $force) {
                if ($previous->etag !== null) {
                    $headers['If-None-Match'] = $previous->etag;
                }

                if ($previous->last_modified !== null) {
                    $headers['If-Modified-Since'] = $previous->last_modified;
                }
            }

            $response = $this->request($link['url'], $headers);

            if ($response->status() === 304 && $previous !== null) {
                $path = (string) $previous->storage_path;
                $this->log($batch, $link, 'not_modified', $previous->etag, $previous->last_modified, $previous->sha256, $previous->bytes, $path, $listDate);
                $files[] = ['path' => $disk->path($path), 'label' => $link['label']];

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException("The list file „{$link['label']}“ answered HTTP {$response->status()}.");
            }

            $body = $response->body();

            if (strlen($body) > (int) config('licences.komora.max_bytes', 20 * 1024 * 1024)) {
                throw new RuntimeException("The list file „{$link['label']}“ is larger than allowed.");
            }

            if (! str_starts_with($body, '%PDF')) {
                throw new RuntimeException("The list file „{$link['label']}“ is not a PDF.");
            }

            $path = $directory.'/'.$batch.'/'.($index + 1).'-'.Str::slug(Str::ascii($link['label'])).'.pdf';
            $disk->put($path, $body);
            $sha = hash('sha256', $body);
            $changed = $changed || $previous === null || $previous->sha256 !== $sha;

            $this->log($batch, $link, 'downloaded', $response->header('ETag') ?: null, $response->header('Last-Modified') ?: null, $sha, strlen($body), $path, $listDate);
            $files[] = ['path' => $disk->path($path), 'label' => $link['label']];
        }

        $this->prune($directory);

        return ['batch' => $batch, 'changed' => $changed || $force, 'list_date' => $listDate, 'files' => $files];
    }

    /**
     * The list files the page links to, in page order, with the link text as
     * a label („Список А-В“ → „А-В“).
     *
     * @return list<array{url: string, label: string}>
     */
    public function links(string $html, string $pageUrl): array
    {
        $pattern = (string) config('licences.komora.file_pattern');
        $links = [];

        preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~isu', $html, $anchors, PREG_SET_ORDER);

        foreach ($anchors as $anchor) {
            $href = html_entity_decode($anchor[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match($pattern, $href) !== 1) {
                continue;
            }

            $url = $this->absolute($href, $pageUrl);

            if (isset($links[$url])) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($anchor[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            $label = trim((string) preg_replace('/^список\s*/iu', '', $text));

            if ($label === '') {
                $label = pathinfo(rawurldecode((string) parse_url($url, PHP_URL_PATH)), PATHINFO_FILENAME);
            }

            $links[$url] = ['url' => $url, 'label' => Str::limit($label, 60, '')];
        }

        return array_values($links);
    }

    /**
     * „Листата содржи активни лиценци изготвени заклучно со 2.7.2026 година“,
     * else a date in a file name, else none.
     *
     * @param  list<array{url: string, label: string}>  $links
     */
    public function listDate(string $html, array $links): ?CarbonImmutable
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/заклучно\s+со\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu', $text, $match) === 1
            && checkdate((int) $match[2], (int) $match[1], (int) $match[3])) {
            return CarbonImmutable::create((int) $match[3], (int) $match[2], (int) $match[1])->startOfDay();
        }

        foreach ($links as $link) {
            if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', rawurldecode($link['url']), $match) === 1
                && checkdate((int) $match[2], (int) $match[1], (int) $match[3])) {
                return CarbonImmutable::create((int) $match[3], (int) $match[2], (int) $match[1])->startOfDay();
            }
        }

        return null;
    }

    private function assertAllowed(string $url): void
    {
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        if (! array_key_exists($host, $this->robots)) {
            $response = $this->request("{$scheme}://{$host}/robots.txt");
            // No robots.txt (404) means no restrictions; a server error means we cannot tell.
            $this->robots[$host] = match (true) {
                $response->successful() => $response->body(),
                $response->status() >= 400 && $response->status() < 500 => '',
                default => throw new RuntimeException("robots.txt of {$host} answered HTTP {$response->status()}."),
            };
        }

        $token = strtok((string) config('licences.user_agent'), '/') ?: 'Zdravje360';

        if (! RobotsTxt::allows($this->robots[$host], $token, $path)) {
            throw new RuntimeException("robots.txt of {$host} does not allow {$path}.");
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $url, array $headers = []): Response
    {
        return Http::withHeaders($headers + ['User-Agent' => (string) config('licences.user_agent')])
            ->timeout((int) config('licences.komora.timeout', 60))
            ->get($url);
    }

    private function pause(): void
    {
        $delay = (int) config('licences.komora.request_delay_ms', 2000);

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    /**
     * @param  array{url: string, label: string}  $link
     */
    private function log(string $batch, array $link, string $status, ?string $etag, ?string $lastModified, ?string $sha, ?int $bytes, string $path, ?CarbonImmutable $listDate): void
    {
        KomoraLicenceDownload::query()->create([
            'batch' => $batch,
            'url' => $link['url'],
            'label' => $link['label'],
            'status' => $status,
            'etag' => $etag,
            'last_modified' => $lastModified,
            'sha256' => $sha,
            'bytes' => $bytes,
            'storage_path' => $path,
            'list_date' => $listDate?->toDateString(),
            'fetched_at' => now(),
        ]);
    }

    /**
     * Keep the raw files of the last few batches; a file an older batch
     * holds is deleted unless a kept batch reuses it (after a 304).
     */
    private function prune(string $directory): void
    {
        $keep = max(1, (int) config('licences.komora.keep_snapshots', 3));
        $disk = Storage::disk((string) config('licences.komora.disk', 'local'));

        $keptBatches = KomoraLicenceDownload::query()
            ->select('batch')
            ->groupBy('batch')
            ->orderByRaw('MAX(id) DESC')
            ->limit($keep)
            ->pluck('batch')
            ->all();

        $keptPaths = KomoraLicenceDownload::query()
            ->whereIn('batch', $keptBatches)
            ->whereNotNull('storage_path')
            ->pluck('storage_path')
            ->all();

        $old = KomoraLicenceDownload::query()
            ->whereNotIn('batch', $keptBatches)
            ->whereNotNull('storage_path')
            ->get();

        foreach ($old as $download) {
            $path = (string) $download->storage_path;

            if (! in_array($path, $keptPaths, true) && str_starts_with($path, $directory.'/')) {
                $disk->delete($path);
            }

            $download->forceFill(['storage_path' => null])->save();
        }

        foreach ($disk->directories($directory) as $batchDirectory) {
            if ($disk->files($batchDirectory) === []) {
                $disk->deleteDirectory($batchDirectory);
            }
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
