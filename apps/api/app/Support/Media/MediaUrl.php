<?php

namespace App\Support\Media;

class MediaUrl
{
    public static function resolve(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return self::normalizePublicUrl($pathOrUrl);
        }

        return self::storageUrl($pathOrUrl);
    }

    private static function storageUrl(string $path): string
    {
        return self::normalizePublicUrl('/storage/'.ltrim($path, '/'));
    }

    private static function normalizePublicUrl(string $urlOrPath): string
    {
        $base = rtrim((string) config('app.url'), '/');

        if (str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
            $path = parse_url($urlOrPath, PHP_URL_PATH) ?: '';

            return $base.$path;
        }

        return $base.'/'.ltrim($urlOrPath, '/');
    }
}
