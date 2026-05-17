<?php

namespace App\Support\Media;

class NameInitials
{
    public static function from(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = mb_substr($parts[array_key_last($parts)], 0, 1);

        return mb_strtoupper($first.$last);
    }
}
