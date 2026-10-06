<?php

namespace App\Support\Import;

/**
 * Display casing for upper-case register data ("ЗДРАВСТВЕН ДОМ БЕРОВО").
 * The raw value stays in the source record; staff can correct (and lock)
 * any result that reads wrong.
 */
final class TextCase
{
    /** Abbreviations kept in capitals inside institution names. */
    private const ACRONYMS = [
        'ЈЗУ', 'ПЗУ', 'ЗУ', 'ЗД', 'УК', 'ГОБ', 'КБ', 'ОБ', 'ДООЕЛ', 'ДОО', 'ТП', 'ЈЗО', 'ПЗЗ', 'СКЗЗ', 'БЗЗ',
        'ЦЈЗ', 'ИЈЗ', 'МАНУ', 'ПЕТ', 'РЕ', 'ДПТУ', 'ДТУ', 'ДППУ', 'АД', 'ЛУ', 'УКИМ', 'СВ',
    ];

    /** Short function words written in lower case inside a name. */
    private const LOWER = ['И', 'ЗА', 'СО', 'НА', 'ВО', 'ОД', 'ПО', 'ДО', 'БР', 'БР.'];

    public static function person(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return (string) preg_replace_callback(
            '/[\p{L}\']+/u',
            fn (array $match): string => self::capitalise($match[0]),
            mb_strtolower($name, 'UTF-8'),
        );
    }

    /**
     * Sentence-style: "ЗДРАВСТВЕН ДОМ БЕРОВО" → "Здравствен дом Берово" is not
     * achievable without knowing which words are proper names, so every word
     * is capitalised except function words, and acronyms stay upper case:
     * "ЈЗУ ЗДРАВСТВЕН ДОМ БЕРОВО" → "ЈЗУ Здравствен Дом Берово".
     */
    public static function institution(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '' || self::hasLowerCase($name)) {
            return $name;
        }

        $words = explode(' ', $name);

        foreach ($words as $index => $word) {
            $bare = trim($word, '„“"\'(),.-');

            if (in_array($bare, self::ACRONYMS, true) || preg_match('/\d/u', $bare) === 1) {
                continue;
            }

            if ($index > 0 && in_array($bare, self::LOWER, true)) {
                $words[$index] = mb_strtolower($word, 'UTF-8');

                continue;
            }

            $words[$index] = (string) preg_replace_callback(
                '/[\p{L}]+/u',
                fn (array $match): string => self::capitalise(mb_strtolower($match[0], 'UTF-8')),
                $word,
                1,
            );
            $words[$index] = (string) preg_replace_callback(
                '/(?<=[\p{L}])([\p{Lu}]+)/u',
                fn (array $match): string => mb_strtolower($match[0], 'UTF-8'),
                $words[$index],
            );
        }

        return implode(' ', $words);
    }

    public static function place(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::hasLowerCase($name) ? trim($name) : self::institution($name);
    }

    private static function capitalise(string $word): string
    {
        return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($word, 1, null, 'UTF-8');
    }

    private static function hasLowerCase(string $value): bool
    {
        return preg_match('/\p{Ll}/u', $value) === 1;
    }
}
