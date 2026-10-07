<?php

namespace App\Services\Triage\V2;

use App\Models\SpecialtyAlias;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;

/**
 * The specialty keys an outcome's `care.specialties` may name: the groups of
 * database/seeders/data/licence_specialty_groups.php. A key is not always a
 * catalogue slug — the ФЗОМ import names its specialties differently
 * (`opsta-medicina` → `opshta-medicina`) — so catalogueSlugs() lists the
 * slugs a key may stand for, and OutcomePresenter links the first published
 * one.
 */
final class SpecialtyGroups
{
    /** @var array<string, string>|null key => readable Macedonian name */
    private static ?array $names = null;

    /** @var array<string, list<string>>|null key => the group's Комора and ФЗОМ wordings */
    private static ?array $wordings = null;

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

    /**
     * The catalogue slugs a key may stand for, most specific first: the key
     * itself (where it is already one of our slugs), then the slugs the ФЗОМ
     * import gives the group's wordings (FzomSpecialtyCatalog). A staff link
     * in licence_specialty_mappings is checked by the caller before these.
     *
     * @return list<string>
     */
    public static function catalogueSlugs(string $key): array
    {
        self::names();
        $slugs = [$key];

        foreach (self::$wordings[$key] ?? [] as $wording) {
            $slug = FzomSpecialtyCatalog::defaultFor(SpecialtyAlias::keyFor($wording));

            if ($slug !== null && $slug !== FzomSpecialtyCatalog::EXCLUDED) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
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
        $wordings = [];

        foreach ($groups as $group) {
            // Pharmacists, dentists and other professions carry no group.
            if (! is_string($group['group'])) {
                continue;
            }

            // ФЗОМ wording first: it is what the import's catalogue is built from.
            $wordings[$group['group']] = array_merge($wordings[$group['group']] ?? [], $group['fzom'] ?? [], $group['komora'] ?? []);

            if (isset($names[$group['group']])) {
                continue;
            }

            $wording = $group['komora'][0] ?? $group['fzom'][0] ?? $group['group'];
            $wording = mb_strtolower($wording);
            $names[$group['group']] = mb_strtoupper(mb_substr($wording, 0, 1)).mb_substr($wording, 1);
        }

        self::$wordings = $wordings;

        return self::$names = $names;
    }
}
