<?php

namespace App\Support\Licences;

use Illuminate\Support\Facades\DB;

/**
 * The reviewed specialty mapping shipped in
 * database/seeders/data/licence_specialty_groups.php, as
 * licence_specialty_mappings rows.
 *
 * Inserted by the 2026_10_15_110000 migration with insertOrIgnore on
 * (source, source_key), so a row staff edited is never overwritten by a
 * later run — only wording that was never there is added.
 */
final class LicenceSpecialtySeedList
{
    /** The sources whose wording is mapped: the Комора list and the ФЗОМ files. */
    public const SOURCES = ['komora', 'fzom'];

    /**
     * @return list<array{source: string, source_text: string, source_key: string, group_key: string|null, compatible_groups: string|null, is_ignored: bool}>
     */
    public static function rows(): array
    {
        /** @var list<array{group: string|null, compatible?: list<string>, ignored?: bool, komora: list<string>, fzom: list<string>}> $groups */
        $groups = require database_path('seeders/data/licence_specialty_groups.php');
        $rows = [];

        foreach ($groups as $group) {
            foreach (self::SOURCES as $source) {
                foreach ($group[$source] as $text) {
                    $rows[] = [
                        'source' => $source,
                        'source_text' => $text,
                        'source_key' => SpecialtyKey::for($text),
                        'group_key' => $group['group'],
                        'compatible_groups' => ($group['compatible'] ?? []) === [] ? null : json_encode($group['compatible']),
                        'is_ignored' => (bool) ($group['ignored'] ?? false),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Insert the rows that are not there yet, linking a group to our
     * specialty with the same slug when there is one.
     */
    public static function insertMissing(): void
    {
        $now = now();
        $specialtyIds = DB::table('specialties')->whereNull('deleted_at')->pluck('id', 'slug');

        $rows = array_map(static fn (array $row): array => $row + [
            'specialty_id' => $row['group_key'] !== null ? ($specialtyIds[$row['group_key']] ?? null) : null,
            'reviewed_at' => $now,
            'notes' => 'Shipped mapping (Комора list 02.07.2026, ФЗОМ files 06.10.2026).',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::rows());

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('licence_specialty_mappings')->insertOrIgnore($chunk);
        }
    }
}
