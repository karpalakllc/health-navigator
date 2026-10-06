<?php

namespace App\Support;

use Normalizer;

/**
 * Turns a raw search query into the form kept in the daily aggregates.
 *
 * Deliberately NOT script-normalised (Latin → Cyrillic): the transliteration in
 * MacedonianSearchVariants is lossy and would turn English or brand terms
 * ("cardiologist", "ibuprofen") into Cyrillic nonsense in the admin view. The
 * directory search itself is script-insensitive, so nothing depends on it here.
 */
final class SearchTermNormalizer
{
    public const MAX_LENGTH = 64;

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        // Control and format characters never belong in a term and would only
        // make two identical-looking rows count separately.
        $value = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);
        $value = mb_strtolower(trim($value));
        $value = rtrim(mb_substr($value, 0, self::MAX_LENGTH));

        if (mb_strlen($value) < SearchQuery::MIN_LENGTH) {
            return null;
        }

        return $value;
    }
}
