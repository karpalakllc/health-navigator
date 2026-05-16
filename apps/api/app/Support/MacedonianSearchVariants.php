<?php

namespace App\Support;

/**
 * Builds search term variants so Latin and Cyrillic queries match the same records
 * (e.g. "ana" matches "Ана", "скопје" matches "skopje").
 */
final class MacedonianSearchVariants
{
    /** @var array<string, string> longest Latin digraphs / trigraphs first */
    private const LATIN_MULTI_TO_CYRILLIC = [
        'dzh' => 'џ',
        'lj' => 'љ',
        'nj' => 'њ',
        'gj' => 'ѓ',
        'kj' => 'ќ',
        'zh' => 'ж',
        'ch' => 'ч',
        'sh' => 'ш',
        'dz' => 'ѕ',
    ];

    /** @var array<string, string> */
    private const LATIN_SINGLE_TO_CYRILLIC = [
        'a' => 'а',
        'b' => 'б',
        'c' => 'ц',
        'd' => 'д',
        'e' => 'е',
        'f' => 'ф',
        'g' => 'г',
        'h' => 'х',
        'i' => 'и',
        'j' => 'ј',
        'k' => 'к',
        'l' => 'л',
        'm' => 'м',
        'n' => 'н',
        'o' => 'о',
        'p' => 'п',
        'r' => 'р',
        's' => 'с',
        't' => 'т',
        'u' => 'у',
        'v' => 'в',
        'z' => 'з',
    ];

    /** @var array<string, string> longest Cyrillic sequences first */
    private const CYRILLIC_MULTI_TO_LATIN = [
        'љ' => 'lj',
        'њ' => 'nj',
        'џ' => 'dzh',
        'ѓ' => 'gj',
        'ќ' => 'kj',
        'ж' => 'zh',
        'ч' => 'ch',
        'ш' => 'sh',
        'ѕ' => 'dz',
        'ј' => 'j',
    ];

    /** @var array<string, string> */
    private const CYRILLIC_SINGLE_TO_LATIN = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'з' => 'z',
        'и' => 'i',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
    ];

    /**
     * Distinct search strings to OR together (always includes the original term).
     *
     * @return list<string>
     */
    public static function variants(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $latinized = self::cyrillicToLatin($term);
        $cyrillicized = self::latinToCyrillic($term);

        $set = [$term];
        if ($latinized !== '' && $latinized !== $term) {
            $set[] = $latinized;
        }
        if ($cyrillicized !== '' && $cyrillicized !== $term) {
            $set[] = $cyrillicized;
        }

        return array_values(array_unique($set));
    }

    public static function latinToCyrillic(string $input): string
    {
        $s = mb_strtolower($input);
        $len = mb_strlen($s);
        $out = '';
        $keys = array_keys(self::LATIN_MULTI_TO_CYRILLIC);
        usort($keys, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        for ($i = 0; $i < $len;) {
            $matched = false;
            $rest = mb_substr($s, $i);
            foreach ($keys as $lat) {
                if (str_starts_with($rest, $lat)) {
                    $out .= self::LATIN_MULTI_TO_CYRILLIC[$lat];
                    $i += mb_strlen($lat);
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }

            $ch = mb_substr($s, $i, 1);
            $i++;

            if (isset(self::LATIN_SINGLE_TO_CYRILLIC[$ch])) {
                $out .= self::LATIN_SINGLE_TO_CYRILLIC[$ch];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }

    public static function cyrillicToLatin(string $input): string
    {
        $s = mb_strtolower($input);
        $len = mb_strlen($s);
        $out = '';
        $keys = array_keys(self::CYRILLIC_MULTI_TO_LATIN);
        usort($keys, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        for ($i = 0; $i < $len;) {
            $matched = false;
            $rest = mb_substr($s, $i);
            foreach ($keys as $cyr) {
                if (str_starts_with($rest, $cyr)) {
                    $out .= self::CYRILLIC_MULTI_TO_LATIN[$cyr];
                    $i += mb_strlen($cyr);
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }

            $ch = mb_substr($s, $i, 1);
            $i++;

            if (isset(self::CYRILLIC_SINGLE_TO_LATIN[$ch])) {
                $out .= self::CYRILLIC_SINGLE_TO_LATIN[$ch];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
