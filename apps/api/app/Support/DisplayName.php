<?php

namespace App\Support;

/**
 * The name shown publicly next to reviews and forum posts.
 *
 * `users.name` is the person's real name and stays private (account page,
 * admin panel, mail). Everything public renders the display name instead —
 * on a health platform, a review or a forum question must not carry a full
 * legal name unless the person chose to show it.
 */
final class DisplayName
{
    public const MAX_LENGTH = 40;

    /**
     * Unicode letters (with combining marks), spaces, and . - ' — starting
     * with a letter, so it cannot be blank or pure punctuation.
     */
    public const PATTERN = "/^\\p{L}\\p{M}*(?:[\\p{L}\\p{M} .'\\-])*$/u";

    /** Trim and collapse runs of whitespace to one space. */
    public static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * First word + initial of the last word ("Марија Костовска" → "Марија К.");
     * a single word is used as it is. Mirrors suggestDisplayName() on the web.
     */
    public static function suggest(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '';
        }

        $first = $parts[0];

        if (count($parts) === 1) {
            return mb_substr($first, 0, self::MAX_LENGTH);
        }

        $initial = mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1)).'.';

        // Keep room for " X." so the initial survives a very long first word.
        $first = mb_substr($first, 0, self::MAX_LENGTH - mb_strlen($initial) - 1);

        return $first.' '.$initial;
    }
}
