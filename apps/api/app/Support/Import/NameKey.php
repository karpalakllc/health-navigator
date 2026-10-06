<?php

namespace App\Support\Import;

use App\Support\MacedonianSearchVariants;

/**
 * Matching keys for people's names across sources (ФЗОМ, Лекарска комора,
 * staff-entered profiles). Stored in doctors.name_key / name_key_sorted.
 *
 * - upper case, Cyrillic: Latin look-alike letters typed into Cyrillic words
 *   ("A", "E", "O", "J"…) are folded to their Cyrillic twins, and a wholly
 *   Latin word is transliterated;
 * - a hyphen and a space in double surnames are the same;
 * - punctuation and academic prefixes („Д-р“, „Проф.“…) are dropped;
 * - whitespace is collapsed.
 *
 * sorted() orders the tokens, so "ПЕТРОВ ИВАН" and "ИВАН ПЕТРОВ" meet: the
 * sources disagree on given-name-first order.
 */
final class NameKey
{
    /** @var array<string, string> Latin capitals that look like Cyrillic ones */
    private const LATIN_LOOKALIKES = [
        'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'I' => 'И',
        'J' => 'Ј', 'K' => 'К', 'M' => 'М', 'O' => 'О', 'P' => 'Р', 'S' => 'Ѕ',
        'T' => 'Т', 'X' => 'Х', 'Y' => 'У',
    ];

    /** @var list<string> abbreviated titles some sources prefix to names (upper case, without the dot) */
    private const TITLES = [
        'ДР', 'ПРОФ', 'ДОЦ', 'АСС', 'АС', 'МР', 'СПЕЦ', 'СУБСПЕЦ', 'ПРИМ', 'НАСЛ',
    ];

    public static function for(string $name): string
    {
        $upper = mb_strtoupper(trim($name), 'UTF-8');
        $kept = [];

        foreach (preg_split('/\s+/u', $upper) ?: [] as $word) {
            // „Д-р“ / „М-р“ carry a hyphen, „Проф.“ / „Доц.“ a dot; a bare
            // „ДР“ could be a name, so only the punctuated forms are titles.
            if (in_array($word, ['Д-Р', 'М-Р', 'Д-Р.', 'М-Р.'], true)
                || (str_ends_with($word, '.') && in_array(rtrim($word, '.'), self::TITLES, true))) {
                continue;
            }

            if (preg_match('/\p{Cyrillic}/u', $word) === 1) {
                $word = strtr($word, self::LATIN_LOOKALIKES);
            }

            foreach (preg_split('/[\-‐‑–—]+/u', $word) ?: [] as $part) {
                $bare = preg_replace('/[^\p{L}\p{N}]/u', '', $part) ?? '';

                // A wholly Latin token is a transliterated name, not a typo.
                if ($bare !== '' && preg_match('/\p{Cyrillic}/u', $bare) !== 1) {
                    $bare = mb_strtoupper(MacedonianSearchVariants::latinToCyrillic(mb_strtolower($bare, 'UTF-8')), 'UTF-8');
                }

                if ($bare !== '') {
                    $kept[] = $bare;
                }
            }
        }

        return implode(' ', $kept);
    }

    public static function sorted(string $name): string
    {
        $tokens = explode(' ', self::for($name));
        sort($tokens, SORT_STRING);

        return trim(implode(' ', $tokens));
    }
}
