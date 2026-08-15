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
            // an APP_URL change). Re-base those onto the configured media disk. Anything
            // else is a genuinely external asset and must survive untouched — including
            // its query string, which the previous implementation silently discarded.
            $path = parse_url($pathOrUrl, PHP_URL_PATH) ?: '';

            if (str_starts_with($path, '/storage/')) {
                return self::diskUrl(substr($path, strlen('/storage/')));
            }

            return $pathOrUrl;
        }

        return self::diskUrl($pathOrUrl);
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
