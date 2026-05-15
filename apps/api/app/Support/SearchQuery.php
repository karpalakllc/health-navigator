<?php

namespace App\Support;

final class SearchQuery
{
    public const MIN_LENGTH = 2;

    /**
     * Normalize list filter `q`: trim; return null when empty or shorter than minimum.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || mb_strlen($trimmed) < self::MIN_LENGTH) {
            return null;
        }

        return $trimmed;
    }
}
