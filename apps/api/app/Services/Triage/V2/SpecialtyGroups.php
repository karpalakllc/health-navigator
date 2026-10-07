<?php

namespace App\Services\Triage\V2;

/**
 * The specialty keys an outcome's `care.specialties` may name: the groups of
 * database/seeders/data/licence_specialty_groups.php. Where a key equals one
 * of our specialties' slugs the guidance links to the filtered directory.
 */
final class SpecialtyGroups
{
    /** @var array<string, string>|null key => readable Macedonian name */
    private static ?array $names = null;

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::names());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::names());
    }

    public static function name(string $key): string
    {
        return self::names()[$key] ?? $key;
    }

    /** @return array<string, string> */
    private static function names(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        /** @var list<array{group: string|null, komora?: list<string>, fzom?: list<string>}> $groups */
        $groups = require database_path('seeders/data/licence_specialty_groups.php');
        $names = [];

        foreach ($groups as $group) {
            // Pharmacists, dentists and other professions carry no group.
            if (! is_string($group['group']) || isset($names[$group['group']])) {
                continue;
            }

            $wording = $group['komora'][0] ?? $group['fzom'][0] ?? $group['group'];
            $wording = mb_strtolower($wording);
            $names[$group['group']] = mb_strtoupper(mb_substr($wording, 0, 1)).mb_substr($wording, 1);
        }

        return self::$names = $names;
    }
}
