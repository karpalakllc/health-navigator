<?php

namespace App\Support\Import\Pharmacies;

use App\Support\Import\NameKey;
use App\Support\Import\TextCase;
use App\Support\MacedonianSearchVariants;

/**
 * Towns and pharmacy names of ФЗОМ's on-duty schedule, as display values and
 * as matching keys (NameKey: upper case, Cyrillic, punctuation dropped).
 */
final class OnDutyNames
{
    /** Abbreviated towns in the schedule => full name. */
    private const TOWN_ALIASES = [
        'М.БРОД' => 'Македонски Брод',
        'М. БРОД' => 'Македонски Брод',
        'М.КАМЕНИЦА' => 'Македонска Каменица',
        'М. КАМЕНИЦА' => 'Македонска Каменица',
        'К.ПАЛАНКА' => 'Крива Паланка',
        'Д.ХИСАР' => 'Демир Хисар',
        'Д. ХИСАР' => 'Демир Хисар',
        'Д.КАПИЈА' => 'Демир Капија',
        'СВ.НИКОЛЕ' => 'Свети Николе',
        'СВ. НИКОЛЕ' => 'Свети Николе',
    ];

    /** Words that do not tell pharmacies apart („ПЗУ Аптека …“). */
    private const NOISE = ['ПЗУ', 'ЈЗУ', 'ЗУ', 'АПТЕКА', 'АПТЕКИ', 'PZU', 'APTEKA'];

    /**
     * „СКОПЈЕ-АЕРОДРОМ“ → [„Скопје“, „Аеродром“]; „М.БРОД“ → [„Македонски
     * Брод“, null]; „БИТОЛА“ → [„Битола“, null].
     *
     * @return array{0: string, 1: string|null}
     */
    public static function town(string $raw): array
    {
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $upper = mb_strtoupper($raw, 'UTF-8');

        if (isset(self::TOWN_ALIASES[$upper])) {
            return [self::TOWN_ALIASES[$upper], null];
        }

        $parts = preg_split('/\s*[-–—]\s*/u', $raw, 2) ?: [$raw];
        $town = (string) TextCase::place($parts[0]);
        $municipality = isset($parts[1]) && trim($parts[1]) !== '' ? TextCase::place($parts[1]) : null;

        return [$town, $municipality];
    }

    /** Matching key of a town (or of a directory city like „Скопје - Карпош“). */
    public static function townKey(string $town): string
    {
        $base = preg_split('/\s+[-–—]\s+|\s*[-–—]\s*(?=\p{Lu})/u', trim($town), 2)[0] ?? $town;
        $upper = mb_strtoupper(trim($base), 'UTF-8');

        if (isset(self::TOWN_ALIASES[$upper])) {
            $base = self::TOWN_ALIASES[$upper];
        }

        if (preg_match('/\p{Cyrillic}/u', $base) !== 1) {
            $base = MacedonianSearchVariants::latinToCyrillic(mb_strtolower($base, 'UTF-8'));
        }

        return mb_substr(NameKey::for($base), 0, 64);
    }

    /**
     * The name as shown: spaces collapsed, the Latin twin after „/“ dropped
     * („ЗЕГИН / ZEGIN“ → „ЗЕГИН“).
     */
    public static function displayName(string $raw): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $parts = preg_split('#\s+/\s+#u', $name, 2) ?: [$name];

        return mb_substr(trim($parts[0]), 0, 255);
    }

    /**
     * Matching key: without „ПЗУ“, „Аптека“, quotes and the town's own name
     * („ПЗУ Аптека „Роса Вита“ Битола“ → „РОСА ВИТА“).
     */
    public static function pharmacyKey(string $name, string $town): string
    {
        $key = NameKey::for(self::displayName($name));
        $townKey = self::townKey($town);
        $words = array_values(array_filter(
            explode(' ', $key),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::NOISE, true),
        ));

        // The town at the end („… БИТОЛА“, „… ГОСТИВАР“), not inside the name.
        $townWords = explode(' ', $townKey);

        if (count($words) > count($townWords) && array_slice($words, -count($townWords)) === $townWords) {
            $words = array_slice($words, 0, -count($townWords));
        }

        return mb_substr(implode(' ', $words), 0, 255);
    }
}
