<?php

namespace App\Support\Import\Facilities;

use App\Support\Import\Names\FacilityName;
use App\Support\MacedonianSearchVariants;

/**
 * Comparison keys for institutions described by two sources in different
 * spellings (the ФЗОМ register: capitals, abbreviations, the municipality;
 * the institution's website: full names, often in Latin script or with a
 * former or brand name in brackets). Used by FacilityDuplicateMatcher.
 *
 * - names: upper-case Cyrillic tokens (a wholly Latin word is
 *   transliterated, a few English words of clinic names translated), the
 *   legal form, function words, titles and the town removed; tokens split in
 *   distinctive ones (what identifies the institution) and generic ones
 *   („клиника“, „ординација“, „дентал“…), each name with its variants (the
 *   part before a dash, a quoted or bracketed name);
 * - addresses: the street words before the house number, and that number
 *   (digits only; „б.б.“ is a number of its own);
 * - phones: the national significant number (no 00389/+389/0 prefix);
 * - hosts: the website host without „www.“.
 */
final class FacilityKeys
{
    /** Legal forms, function words, titles: never part of the comparison. */
    private const STOP = [
        'ЈЗУ', 'ПЗУ', 'ЗУ', 'ЈУ', 'ЈЗО', 'ДОО', 'ДООЕЛ', 'АД', 'ТП', 'ДПТУ', 'ДОО.',
        'И', 'ЗА', 'СО', 'НА', 'ВО', 'ОД', 'ПО', 'ДО', 'С', 'КАЈ', 'ЗА.', 'Е', 'ТЕ',
        'Д', 'Р', 'ДР', 'ДОКТОР', 'ПРОФ', 'ДОЦ', 'ПРИМ', 'ПРИМАРИУС', 'АКАДЕМИК', 'АСС', 'МР', 'СЦИ', 'СПЕЦ', 'СУБСПЕЦ',
        'СВ', 'СВЕТИ', 'СВЕТА', 'СВЕТ',
        'РЕПУБЛИКА', 'СЕВЕРНА', 'МАКЕДОНИЈА', 'РСМ', 'РМ', 'ПОРАНЕШНО', 'ПОРАНЕШНА', 'ТИМОТ',
        'СКОПЈЕ', 'СК',
    ];

    /** Words that describe a kind of institution, not which one. */
    private const GENERIC = [
        'УК', 'КЛ', 'ОРДИНАЦИЈА', 'ОРДИНАЦИИ', 'КЛИНИКА', 'КЛИНИКИ', 'КЛИНИЧКА', 'КЛИНИЧКИ', 'ПОЛИКЛИНИКА', 'БОЛНИЦА',
        'ЗДРАВСТВЕН', 'ЗДРАВСТВЕНА', 'ДОМ', 'ЦЕНТАР', 'УНИВЕРЗИТЕТСКА', 'УНИВЕРЗИТЕТСКИ', 'ИНСТИТУТ',
        'СПЕЦИЈАЛНА', 'СПЕЦИЈАЛИЗИРАНА', 'СПЕЦИЈАЛИСТИЧКА', 'СПЕЦИЈАЛИСТИЧКИ', 'СТОМАТОЛОШКА', 'СТОМАТОЛОШКИ',
        'СТОМАТОЛОГИЈА', 'ОПШТА', 'МЕДИЦИНА', 'МЕДИЦИНСКИ', 'МЕДИЦИНСКА', 'ДЕНТАЛ', 'ДЕНТАЛНА', 'ДЕНТАЛЕН',
        'ДЕНТ', 'ЛАБОРАТОРИЈА', 'ДИЈАГНОСТИЧКА', 'ДИЈАГНОСТИКА', 'ЗАВОД', 'ОДДЕЛ', 'ЈАВНА', 'ПРИВАТНА',
        'УСТАНОВА', 'ГРУП', 'СТУДИО', 'МЕДИКАЛ', 'ФАМИЛИ', 'ЕСТЕТСКА', 'ЕСТЕТИК', 'ПРОШИРЕНА', 'ДЕЈНОСТ',
    ];

    /** English words of clinic names, as the Macedonian name writes them. */
    private const ENGLISH = [
        'clinic' => 'клиника', 'clinics' => 'клиники', 'medical' => 'медикал', 'center' => 'центар',
        'centre' => 'центар', 'dental' => 'дентал', 'group' => 'груп', 'family' => 'фамили',
        'polyclinic' => 'поликлиника', 'hospital' => 'болница', 'studio' => 'студио', 'dent' => 'дент',
        'and' => 'и', 'of' => '', 'the' => '', 'dr' => '',
    ];

    /** Address words that are not the street's name. */
    private const ADDRESS_STOP = [
        'УЛ', 'УЛИЦА', 'БР', 'БРОЈ', 'БУЛ', 'БУЛЕВАР', 'ЛОК', 'ЛОКАЛ', 'СТАН', 'ВЛЕЗ', 'КАТ', 'ТЦ', 'НАС',
        'НАСЕЛБА', 'С', 'СЕЛО', 'ОПШТИНА', 'КОМПЛЕКС', 'ЗГРАДА', 'ПРИЗЕМЈЕ', 'ТА', 'ТИ', 'МИ', 'ВИ', 'ВА', 'МА',
        'РИ', 'ОД', 'НА', 'ВО', 'И', 'КМ', 'ПАТОТ', 'КОН', 'СВ', 'СВЕТИ', 'МАКЕДОНИЈА', 'СЕВЕРНА', 'РЕПУБЛИКА', 'СОСТАВ',
    ];

    /**
     * The town part of „Скопје - Карпош“ (the register adds the municipality).
     */
    public static function town(?string $town): string
    {
        $first = preg_split('/\s+[-–—]\s+/u', trim((string) $town))[0] ?? '';

        return implode(' ', self::words((string) $first));
    }

    /**
     * Every word of the town and municipality, to drop from names and addresses.
     *
     * @return list<string>
     */
    public static function townWords(?string $town): array
    {
        return array_values(array_unique(self::words((string) $town)));
    }

    /**
     * The variants of a name (whole, before a spaced dash, quoted, in
     * brackets), each as distinctive and generic tokens.
     *
     * @param  list<string>  $townWords
     * @return list<array{distinctive: list<string>, generic: list<string>, compact: string}>
     */
    public static function nameVariants(string $name, array $townWords = []): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $texts = [$name];

        $withoutBrackets = trim((string) preg_replace('/\([^)]*\)/u', ' ', $name));
        $texts[] = $withoutBrackets;

        if (preg_match_all('/\(([^)]+)\)/u', $name, $matches) > 0) {
            array_push($texts, ...$matches[1]);
        }

        if (preg_match_all('/[„“"«]([^„“"”«»]+)[“"”»]/u', $name, $matches) > 0) {
            array_push($texts, ...$matches[1]);
        }

        foreach (preg_split('/\s+[-–—]\s+/u', $withoutBrackets) ?: [] as $part) {
            $texts[] = $part;
        }

        $variants = [];

        foreach (array_unique($texts) as $text) {
            $variant = self::nameTokens($text, $townWords);

            if ($variant['distinctive'] !== [] || count($variant['generic']) >= 2) {
                $variants[$variant['compact'].'|'.implode(' ', $variant['generic'])] = $variant;
            }
        }

        return array_values($variants);
    }

    /**
     * @param  list<string>  $townWords
     * @return array{distinctive: list<string>, generic: list<string>, compact: string}
     */
    public static function nameTokens(string $text, array $townWords = []): array
    {
        // „Кл. за …“, „Орд.“, „Универ.“: the abbreviations the register writes.
        $text = FacilityName::clean($text)->value;
        $distinctive = [];
        $generic = [];
        $all = [];

        foreach (self::words($text, abbreviations: true) as $word) {
            $bare = rtrim($word, '.');

            if (in_array($bare, self::STOP, true) || in_array($bare, $townWords, true) || (mb_strlen($bare) < 2 && ! ctype_digit($bare))) {
                continue;
            }

            $all[] = $bare;

            if (self::isGeneric($bare)) {
                $generic[] = $bare;
            } else {
                $distinctive[] = $word;
            }
        }

        return [
            'distinctive' => array_values(array_unique($distinctive)),
            'generic' => array_values(array_unique($generic)),
            'compact' => implode('', $all),
        ];
    }

    public static function isGeneric(string $word): bool
    {
        foreach (self::GENERIC as $generic) {
            // „Спец.“, „Бол.“, „Орд.“: the register's abbreviations.
            if ($word === $generic || (mb_strlen($word) >= 3 && str_starts_with($generic, $word))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether two name or street tokens are the same word written two ways:
     * 2 when equal, one an abbreviation (with its dot) of the other
     * („Инфек.“ / „инфективни“, „Хир.“ / „хирургија“) or another ending of
     * the word („дентал“ / „дентална“, „гинекологија“ / „гинеколошка“); 1 when one letter apart in a long
     * word („Луканоски“ / „Лукановски“, a near miss, not proof); else 0.
     */
    public static function sameWord(string $a, string $b): int
    {
        if ($a === $b) {
            return 2;
        }

        if (ctype_digit($a) || ctype_digit($b)) {
            return 0;
        }

        // „Инфек.“ / „инфективни“: a word the register cut short, with its dot.
        foreach ([[$a, $b], [$b, $a]] as [$cut, $full]) {
            if (str_ends_with($cut, '.') && ! str_ends_with($full, '.') && mb_strlen($cut) >= 3 && str_starts_with($full, rtrim($cut, '.'))) {
                return 2;
            }
        }

        $a = rtrim($a, '.');
        $b = rtrim($b, '.');

        if ($a === $b) {
            return 2;
        }

        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        $short = min($la, $lb);

        // Another ending („дентал“ / „дентална“), not another word („Вита“ / „Витаксија“).
        if ($short >= 5 && abs($la - $lb) <= 3 && (str_starts_with($a, $b) || str_starts_with($b, $a))) {
            return 2;
        }

        if ($short >= 7 && self::commonPrefix($a, $b) >= 7) {
            return 2;
        }

        if ($short >= 6 && levenshtein(MacedonianSearchVariants::cyrillicToLatin($a), MacedonianSearchVariants::cyrillicToLatin($b)) <= 1) {
            return 1;
        }

        return 0;
    }

    /**
     * How many words of $a have a partner in $b (each partner used once);
     * with $strict only the certain partners (sameWord() = 2).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    public static function matchedWords(array $a, array $b, bool $strict = false): int
    {
        $left = $b;
        $matched = 0;

        foreach ($a as $word) {
            $best = null;

            foreach ($left as $index => $other) {
                $level = self::sameWord($word, $other);

                if ($level === 2 || ($level === 1 && ! $strict && $best === null)) {
                    $best = $index;

                    if ($level === 2) {
                        break;
                    }
                }
            }

            if ($best !== null) {
                $matched++;
                unset($left[$best]);
            }
        }

        return $matched;
    }

    /**
     * Street words and house number of an address.
     *
     * @param  list<string>  $townWords
     * @return array{street: list<string>, number: string|null}|null
     */
    public static function address(?string $address, array $townWords = []): ?array
    {
        $text = trim((string) $address);

        if ($text === '') {
            return null;
        }

        $text = (string) preg_replace('/\([^)]*\)/u', ' ', $text);
        $text = mb_strtoupper($text, 'UTF-8');
        // „ВОДЊАНСКА17“, „БР.34ЛОК.7“: words and numbers glued.
        $text = (string) preg_replace(['/(\p{L})(\d)/u', '/(\d)(\p{L}{2,})/u'], '$1 $2', $text);
        // „б.б.“, „бб“, „Б.Б“: without a number.
        $text = (string) preg_replace('/(?<![\p{L}])Б\.?\s*Б\.?(?![\p{L}])/u', ' ББ ', $text);
        // Postal codes (four digits standing alone).
        $text = (string) preg_replace('/(?<!\d)\d{4}(?!\d)/u', ' ', $text);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $street = [];
        $number = null;
        $seenWord = false;

        foreach ($tokens as $token) {
            $token = self::cyrillic($token);

            if (preg_match('/^(\d+)/u', $token, $digits) === 1) {
                // A number before any street word belongs to the street's
                // name („11 Октомври“, „16-та Македонска бригада“).
                if (! $seenWord) {
                    $street[] = $digits[1];

                    continue;
                }

                $number = $digits[1];

                break;
            }

            if ($token === 'ББ') {
                $number = 'ББ';

                break;
            }

            if (in_array($token, self::ADDRESS_STOP, true) || in_array($token, $townWords, true) || mb_strlen($token) < 2) {
                continue;
            }

            $seenWord = true;
            $street[] = $token;
        }

        if (! $seenWord) {
            // Only numbers: a numbered street and the house number („ул. 117 бр. 4“).
            return count($street) >= 2 ? ['street' => [$street[0]], 'number' => $street[1]] : null;
        }

        return $street === [] ? null : ['street' => $street, 'number' => $number];
    }

    /**
     * 'exact' (same street, same number), 'street' (same street, the number
     * differs or one is missing) or null.
     *
     * @param  array{street: list<string>, number: string|null}|null  $a
     * @param  array{street: list<string>, number: string|null}|null  $b
     */
    public static function compareAddresses(?array $a, ?array $b): ?string
    {
        if ($a === null || $b === null) {
            return null;
        }

        $alphaA = array_values(array_filter($a['street'], fn (string $word): bool => ! ctype_digit($word)));
        $alphaB = array_values(array_filter($b['street'], fn (string $word): bool => ! ctype_digit($word)));
        $shorter = min(count($alphaA), count($alphaB));

        if ($alphaA === [] && $alphaB === []) {
            // Numbered streets („ул. 103 бр. 1“, Тетово).
            if ($a['street'] !== $b['street']) {
                return null;
            }
        } elseif ($shorter === 0 || (self::matchedWords($alphaA, $alphaB) < $shorter && ! self::nearlySame(implode('', $alphaA), implode('', $alphaB)))) {
            return null;
        }

        // A number in the street's name („11 Октомври“) must agree too.
        $digitsA = array_values(array_filter($a['street'], 'ctype_digit'));
        $digitsB = array_values(array_filter($b['street'], 'ctype_digit'));

        if ($digitsA !== [] && $digitsB !== [] && $digitsA !== $digitsB) {
            return null;
        }

        return $a['number'] !== null && $a['number'] === $b['number'] ? 'exact' : 'street';
    }

    /**
     * Long words or glued names one or two letters apart („ХАЏИВАСИЛЕВ
     * ЈАСМИН“ / „ХАЏИ ВАСИЛЕВ ЈАСМИН“ once glued, „Гиновски“ / „Ѓиноски“).
     */
    public static function nearlySame(string $a, string $b): bool
    {
        if (mb_strlen($a) < 8 || mb_strlen($b) < 8) {
            return false;
        }

        return levenshtein(MacedonianSearchVariants::cyrillicToLatin($a), MacedonianSearchVariants::cyrillicToLatin($b)) <= (min(mb_strlen($a), mb_strlen($b)) >= 14 ? 2 : 1);
    }

    /**
     * National significant numbers of the phones in a text or list.
     *
     * @param  list<string|null>  $phones
     * @return list<string>
     */
    public static function phones(array $phones): array
    {
        $keys = [];

        foreach ($phones as $phone) {
            foreach (preg_split('/[,;\/]|\s{2,}/u', (string) $phone) ?: [] as $part) {
                $digits = (string) preg_replace('/\D/', '', $part);
                $digits = (string) preg_replace('/^(00389|389|0)/', '', $digits);

                if (strlen($digits) >= 7 && strlen($digits) <= 9) {
                    $keys[] = $digits;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    public static function host(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = parse_url(preg_match('#^https?://#i', $url) === 1 ? $url : 'http://'.$url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? (string) preg_replace('/^www\./', '', mb_strtolower($host)) : null;
    }

    /**
     * Upper-case words, a wholly Latin word in Cyrillic (English clinic
     * words translated first).
     *
     * @return list<string>
     */
    private static function words(string $text, bool $abbreviations = false): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $words = [];
        preg_match_all('/([\p{L}\p{N}]+)(\.)?/u', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $word = $match[1];

            if (preg_match('/^\p{Latin}+$/u', $word) === 1 && array_key_exists($word, self::ENGLISH)) {
                $word = self::ENGLISH[$word];

                if ($word === '') {
                    continue;
                }
            }

            $word = self::cyrillic(mb_strtoupper($word, 'UTF-8'));
            // A short word with a dot is cut short („Спец.“, „Инфек.“).
            $cut = $abbreviations && ($match[2] ?? '') === '.' && mb_strlen($word) <= 7 && preg_match('/^\p{L}+$/u', $word) === 1;
            $words[] = $cut ? $word.'.' : $word;
        }

        return $words;
    }

    private static function cyrillic(string $word): string
    {
        if (preg_match('/\p{Latin}/u', $word) !== 1) {
            return $word;
        }

        $ascii = strtr(mb_strtolower($word, 'UTF-8'), ['č' => 'ch', 'ć' => 'kj', 'š' => 'sh', 'ž' => 'zh', 'đ' => 'gj', 'ë' => 'e', 'ç' => 'ch']);

        return mb_strtoupper(MacedonianSearchVariants::latinToCyrillic($ascii), 'UTF-8');
    }

    private static function commonPrefix(string $a, string $b): int
    {
        $length = min(mb_strlen($a), mb_strlen($b));

        for ($i = 0; $i < $length; $i++) {
            if (mb_substr($a, $i, 1) !== mb_substr($b, $i, 1)) {
                return $i;
            }
        }

        return $length;
    }
}
