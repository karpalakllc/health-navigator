<?php

namespace App\Support\Usernames;

use App\Support\MacedonianSearchVariants;

/**
 * The comparison forms of a username. The username itself is stored as typed
 * („Marko_S“, „Ана.М“) and shown that way; uniqueness and the blocked and
 * reserved lists work on folded forms, so that look-alikes count as one name.
 *
 * Two forms, because one cannot do both jobs:
 *
 * - key(): what the name *says*. Lower case, Macedonian Cyrillic transliterated
 *   to Latin (Марко = marko), Latin diacritics read the same way (č = ч = ch),
 *   leetspeak folded (m4rk0 = marko), separators dropped (m.a.r.k.o = marko).
 *   Stored in `users.username_normalized`, which carries the unique index.
 * - skeleton(): what the name *looks like*. Cyrillic letters that look like a
 *   Latin letter become that letter (Cyrillic „рара“ = Latin „papa“, „СОСК“ =
 *   „COCK“), and l, 1 and I become i. Transliteration cannot catch these: р is
 *   "r" when read but "p" when seen. Stored in `users.username_skeleton` and
 *   checked as well, so nobody can take a visual copy of someone else's name.
 */
final class UsernameNormalizer
{
    /**
     * Latin letters with diacritics that the username alphabet allows (Albanian
     * ç ë, South Slavic č ć đ š ž), read as the Macedonian transliteration reads
     * the matching Cyrillic letter: Čočev = Кочев = kochev.
     *
     * @var array<string, string>
     */
    private const LATIN_PHONETIC = [
        'ç' => 'ch', 'č' => 'ch', 'ć' => 'kj', 'đ' => 'gj', 'š' => 'sh', 'ž' => 'zh', 'ë' => 'e',
    ];

    /** @var array<string, string> the same letters by shape */
    private const LATIN_VISUAL = [
        'ç' => 'c', 'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z', 'ë' => 'e',
    ];

    /**
     * Cyrillic letters that look like a Latin letter in either case (В/B, Н/H
     * and Т/T are upper-case look-alikes, and a username keeps its case). The
     * rest are transliterated.
     *
     * @var array<string, string>
     */
    private const CYRILLIC_VISUAL = [
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'ѕ' => 's', 'ј' => 'j', 'к' => 'k', 'м' => 'm',
        'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
    ];

    /**
     * Letters outside the username alphabet that imitate one inside it. The
     * alphabet rule already refuses them in a username; folding them too keeps
     * both forms safe for every other input (staff-entered list terms).
     *
     * @var array<string, string>
     */
    private const FOREIGN_HOMOGLYPHS = [
        // Greek
        'α' => 'a', 'ά' => 'a', 'β' => 'b', 'γ' => 'y', 'δ' => 'd', 'ε' => 'e', 'έ' => 'e', 'ζ' => 'z',
        'η' => 'n', 'ή' => 'n', 'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'u',
        'ν' => 'v', 'ο' => 'o', 'ό' => 'o', 'π' => 'n', 'ρ' => 'p', 'σ' => 'o', 'ς' => 'c', 'τ' => 't',
        'υ' => 'u', 'ύ' => 'u', 'φ' => 'f', 'χ' => 'x', 'ω' => 'w', 'ώ' => 'w',
        // Cyrillic letters of other alphabets, and Cyrillic-block Latin look-alikes
        'і' => 'i', 'ї' => 'i', 'й' => 'и', 'ё' => 'е', 'є' => 'е', 'ў' => 'у', 'ґ' => 'г',
        'ђ' => 'ѓ', 'ћ' => 'ќ', 'ы' => 'и', 'э' => 'е', 'ӏ' => 'l', 'ԁ' => 'd', 'ԛ' => 'q',
        'ԝ' => 'w', 'ү' => 'у', 'һ' => 'h', 'щ' => 'шт', 'ъ' => '', 'ь' => '', 'ю' => 'ју', 'я' => 'ја',
        // Latin letters with other diacritics
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ı' => 'i', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ß' => 'ss', 'ł' => 'l', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'ń' => 'n', 'ğ' => 'g', 'ş' => 's', 'ț' => 't', 'ș' => 's', 'ă' => 'a', 'ő' => 'o', 'ű' => 'u',
    ];

    /**
     * Digits and symbols written for letters. Only 0 1 3 4 5 7 can occur in a
     * username; the rest matter for staff-entered terms.
     *
     * @var array<int|string, string>
     */
    private const LEET = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
        '!' => 'i', '|' => 'i', '€' => 'e',
    ];

    /**
     * NFKC (full-width „Ａｄｍｉｎ“ and mathematical „𝐀𝐝𝐦𝐢𝐧“ become „Admin“,
     * decomposed letters compose), then trimmed: the form a username is
     * validated and stored in.
     */
    public static function prepare(string $value): string
    {
        $folded = \Normalizer::normalize($value, \Normalizer::FORM_KC);

        return trim($folded === false ? $value : $folded);
    }

    public static function key(string $value): string
    {
        $s = mb_strtolower(self::prepare($value));
        $s = strtr($s, self::LATIN_PHONETIC);
        $s = strtr($s, self::FOREIGN_HOMOGLYPHS);
        $s = MacedonianSearchVariants::cyrillicToLatin($s);
        $s = strtr($s, self::LEET);

        return self::latinOnly($s);
    }

    public static function skeleton(string $value): string
    {
        $s = mb_strtolower(self::prepare($value));
        $s = strtr($s, self::LATIN_VISUAL);
        $s = strtr($s, self::CYRILLIC_VISUAL);
        $s = strtr($s, self::FOREIGN_HOMOGLYPHS);
        $s = MacedonianSearchVariants::cyrillicToLatin($s);
        $s = strtr($s, self::LEET);
        // Upper-case I and lower-case l are the same stroke.
        $s = strtr($s, ['l' => 'i']);

        return self::latinOnly($s);
    }

    /** Runs of one letter as one: „fuuuck“ → „fuck“. For list matching only. */
    public static function collapse(string $form): string
    {
        return (string) preg_replace('/(.)\1+/', '$1', $form);
    }

    /**
     * The words a username is made of: split at separators („dr.marko“ → dr,
     * marko; „sh1t_x“ → sh1t, x), at every combination of two of the three
     * separators (so the hyphen of „д-р.петар“ keeps „д-р“ whole), and also
     * at letter/digit boundaries („dr1“ → dr, 1), so a digit cannot glue a
     * title to a name while leetspeak inside a word („sh1t“) still reads as
     * one word.
     *
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $s = mb_strtolower(self::prepare($value));
        $tokens = [];

        foreach (['/[\s._\-]+/u', '/[\s._]+/u', '/[\s_\-]+/u', '/[\s.\-]+/u', '/[\s._\-]+|(?<=\p{L})(?=\d)|(?<=\d)(?=\p{L})/u'] as $pattern) {
            array_push($tokens, ...(preg_split($pattern, $s, -1, PREG_SPLIT_NO_EMPTY) ?: []));
        }

        return array_values(array_unique($tokens));
    }

    /** Drop separators, spaces and anything else that is not a-z or a digit. */
    private static function latinOnly(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', $s);
    }
}
