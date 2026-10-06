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
     * Cyrillic and Latin letters, spaces, and . - ' — starting with a letter,
     * so it cannot be blank or pure punctuation. Applied after normalize()
     * (NFKC), so every Macedonian and accented Latin letter arrives
     * precomposed and no combining mark is needed. Excluding marks and other
     * scripts shuts out lookalikes: an invisible combining grapheme joiner
     * ("Ad͏min"), a Greek capital Alpha ("Αdmin").
     */
    public const PATTERN = "/^[\\p{Cyrillic}\\p{Latin}][\\p{Cyrillic}\\p{Latin} .'\\-]*$/u";

    /** Fewer letters than this ("x", "А.") identify nobody. */
    public const MIN_LETTERS = 2;

    /**
     * Titles and roles a member may not claim, in any script: a review "from
     * Д-р Марко" or a forum answer from "Администратор" reads as authoritative
     * on a health platform. Matched after lower-casing, transliteration both
     * ways and stripping . - ' — so "Dr.", "Д-р" and "DR" are all "др".
     *
     * Short terms are whole words only: as prefixes they would catch ordinary
     * names ("Драган", "Тимчо", "Profirovski").
     */
    private const RESERVED_WORDS = ['др', 'dr', 'проф', 'prof', 'тим', 'team'];

    /** Also matched as the start of a word: "Админ123", "Administrator", "Zdravje360". */
    private const RESERVED_PREFIXES = [
        'доктор', 'doctor', 'doktor',
        'админ', 'admin',
        'модератор', 'moderator',
        'поддршка', 'podrshka', 'podrska', 'support',
        'здравје', 'zdravje',
        'официјал', 'oficijal', 'official',
    ];

    /**
     * Why a (normalised) display name is not allowed beyond its character set,
     * or null when it is: `too_short`, `mixed_script` or `reserved`.
     */
    public static function rejection(string $name): ?string
    {
        if (preg_match_all('/\p{L}/u', $name) < self::MIN_LETTERS) {
            return 'too_short';
        }

        foreach (preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            // "Аdmin" (Cyrillic А): a lookalike built to slip past the list below.
            if (preg_match('/\p{Cyrillic}/u', $word) === 1 && preg_match('/\p{Latin}/u', $word) === 1) {
                return 'mixed_script';
            }
        }

        return self::isReserved($name) ? 'reserved' : null;
    }

    private static function isReserved(string $name): bool
    {
        $lower = mb_strtolower($name);

        // "Д-р.Марко" must yield "др", "Ана-Марија" must not yield "ана".
        $tokens = array_merge(
            preg_split('/[\s.]+/u', str_replace(['-', "'"], '', $lower), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            preg_split('/\s+/u', str_replace(['.', '-', "'"], '', $lower), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            preg_split("/[\\s.'\\-]+/u", $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        foreach (array_unique($tokens) as $token) {
            foreach (self::scriptForms($token) as $form) {
                foreach (self::RESERVED_WORDS as $word) {
                    if (in_array($form, self::scriptForms($word), true)) {
                        return true;
                    }
                }

                foreach (self::RESERVED_PREFIXES as $prefix) {
                    foreach (self::scriptForms($prefix) as $prefixForm) {
                        if (str_starts_with($form, $prefixForm)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * A word as written plus its Cyrillic and Latin transliterations.
     *
     * @return list<string>
     */
    private static function scriptForms(string $word): array
    {
        return array_values(array_unique([
            $word,
            MacedonianSearchVariants::latinToCyrillic($word),
            MacedonianSearchVariants::cyrillicToLatin($word),
        ]));
    }

    /**
     * NFKC, then trim and collapse runs of whitespace to one space.
     *
     * NFKC folds compatibility forms into the letters they imitate — full-width
     * "Ａｄｍｉｎ" and mathematical "𝐀𝐝𝐦𝐢𝐧" become "Admin" — so the reserved-term
     * check sees what a reader sees, and composes decomposed input ("е" + U+0301)
     * into the precomposed letter PATTERN accepts.
     */
    public static function normalize(string $value): string
    {
        $folded = \Normalizer::normalize($value, \Normalizer::FORM_KC);

        return trim((string) preg_replace('/\s+/u', ' ', $folded === false ? $value : $folded));
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
