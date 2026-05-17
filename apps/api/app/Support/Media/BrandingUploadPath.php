<?php

namespace App\Support\Media;

use Illuminate\Support\Arr;

class BrandingUploadPath
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            if (str_contains($value, 'media/')) {
                return $value;
            }

            $trimmed = trim($value);

            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? self::normalize($decoded) : null;
            }

            return $value;
        }

        if (is_array($value)) {
            foreach (Arr::flatten($value) as $item) {
                if (is_string($item) && str_contains($item, 'media/')) {
                    return $item;
                }
            }

            foreach (array_keys($value) as $key) {
                if (is_string($key) && str_contains($key, 'media/')) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function isCorruptState(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return str_starts_with($trimmed, '{') && ! str_contains($value, 'media/');
        }

        if (! is_array($value)) {
            return false;
        }

        return self::normalize($value) === null;
    }
}
