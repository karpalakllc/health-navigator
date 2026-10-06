<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public static function resolve(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            // Legacy rows stored a full URL pointing at our own /storage mount (e.g. after
            // an APP_URL change, or before media moved to object storage). Re-base those
            // onto the configured media disk. Anything else is a genuinely external asset
            // and must survive untouched — including its query string, and including a
            // foreign host that merely happens to use a /storage/ path.
            $path = parse_url($pathOrUrl, PHP_URL_PATH) ?: '';
            $host = parse_url($pathOrUrl, PHP_URL_HOST);

            if (str_starts_with($path, '/storage/') && self::isOwnHost($host)) {
                return self::diskUrl(substr($path, strlen('/storage/')));
            }

            return $pathOrUrl;
        }

        return self::diskUrl($pathOrUrl);
    }

    /**
     * Hosts our own local /storage mount has been served from: loopback (every
     * development database) and the API's current origin. Not the media disk's
     * host — a bucket URL is already a final URL.
     */
    private static function isOwnHost(mixed $host): bool
    {
        if (! is_string($host) || $host === '') {
            return false;
        }

        $own = ['localhost', '127.0.0.1', '[::1]'];

        foreach ([config('app.url'), config('filesystems.disks.public.url')] as $url) {
            $ownHost = parse_url((string) $url, PHP_URL_HOST);

            if (is_string($ownHost) && $ownHost !== '') {
                $own[] = strtolower($ownHost);
            }
        }

        return in_array(strtolower($host), $own, true);
    }

    /**
     * Ask the disk for the URL rather than assuming a local /storage mount, so that
     * switching MEDIA_DISK to s3 (documented as supported in config/media.php) produces
     * bucket URLs instead of 404s on the API host.
     */
    private static function diskUrl(string $path): string
    {
        return Storage::disk(config('media.disk'))->url(ltrim($path, '/'));
    }
}
