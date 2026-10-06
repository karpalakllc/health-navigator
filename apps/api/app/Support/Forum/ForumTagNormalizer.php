<?php

namespace App\Support\Forum;

use Illuminate\Support\Str;
use Normalizer;

/**
 * One spelling-independent form per forum keyword.
 *
 * Macedonians search in Cyrillic, in Latin with diacritics ("prošireni") and,
 * most often, in "shaved" Latin ("prosireni" or "proshireni"). A keyword is
 * stored once, in the form its author wrote, and these helpers derive:
 *
 *   name()     — the display form: trimmed, lower-case, no punctuation
 *   latin()    — diacritic-free Latin ("prosireni veni"), shown once in the
 *                meta description so Latin-script searches match
 *   matchKey() — latin() with the digraphs folded (sh→s, zh→z, ch→c, kj→k,
 *                gj→g, dzh→dz), so every spelling above meets on one key
 */
final class ForumTagNormalizer
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 40;

    /** Per topic: enough to describe it, too few to stuff. */
    public const MAX_PER_TOPIC = 5;

    /** @var array<string, string> Cyrillic → the Latin people type without diacritics */
    private const CYRILLIC_TO_ASCII = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'ѓ' => 'g',
        'е' => 'e', 'ж' => 'z', 'з' => 'z', 'ѕ' => 'dz', 'и' => 'i', 'ј' => 'j',
        'к' => 'k', 'л' => 'l', 'љ' => 'lj', 'м' => 'm', 'н' => 'n', 'њ' => 'nj',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'ќ' => 'k',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'c', 'џ' => 'dz',
        'ш' => 's',
    ];

    /** @var array<string, string> longest first */
    private const DIGRAPH_FOLDS = [
        'dzh' => 'dz',
        'sh' => 's',
        'zh' => 'z',
        'ch' => 'c',
        'kj' => 'k',
        'gj' => 'g',
    ];

    /**
     * The stored display form, or null when nothing usable is left.
     */
    public static function name(string $raw): ?string
    {
        $value = self::clean($raw);
        $length = mb_strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return null;
        }

        // A bare number is not a keyword.
        if (preg_match('/^[\p{N}\- ]+$/u', $value) === 1) {
            return null;
        }

        return $value;
    }

    /**
     * Lower-case words without punctuation, any length (also used to compare
     * doctor and facility names with keywords).
     */
    public static function clean(string $raw): string
    {
        $value = Normalizer::normalize($raw, Normalizer::FORM_C) ?: $raw;
        $value = mb_strtolower($value);
        $value = ltrim(trim($value), '#');
        // Letters (any script), digits, spaces and hyphens; anything else
        // (punctuation, emoji, markup) separates words.
        $value = preg_replace('/[^\p{L}\p{N}\- ]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s*-\s*/u', '-', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '', ' -');
    }

    /**
     * Diacritic-free Latin spelling of a normalised name.
     */
    public static function latin(string $name): string
    {
        $out = '';

        foreach (mb_str_split(mb_strtolower($name)) as $char) {
            $out .= self::CYRILLIC_TO_ASCII[$char] ?? $char;
        }

        // š → s, ž → z, ǵ → g, ḱ → k …
        return Str::ascii($out);
    }

    public static function matchKey(string $name): string
    {
        $latin = str_replace('-', ' ', self::latin($name));

        return strtr($latin, self::DIGRAPH_FOLDS);
    }

    public static function slug(string $name): string
    {
        return Str::slug(self::latin($name)) ?: 'tag';
    }

    /**
     * Normalised, de-duplicated (by match key) and capped list.
     *
     * @param  iterable<mixed>  $raw
     * @return list<string>
     */
    public static function list(iterable $raw): array
    {
        $names = [];

        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }

            $name = self::name($value);

            if ($name === null) {
                continue;
            }

            $names[self::matchKey($name)] ??= $name;

            if (count($names) === self::MAX_PER_TOPIC) {
                break;
            }
        }

        return array_values($names);
    }
}
