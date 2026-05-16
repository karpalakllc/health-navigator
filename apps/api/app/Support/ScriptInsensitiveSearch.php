<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies ILIKE / case-insensitive LIKE for multiple Macedonian script variants of a search term.
 */
final class ScriptInsensitiveSearch
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function whereColumnMatches(Builder $query, string $column, string $term): Builder
    {
        $variants = MacedonianSearchVariants::variants($term);
        if ($variants === []) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $grammar = $query->getGrammar();
        $wrapped = $grammar->wrap($column);

        return $query->where(function (Builder $inner) use ($column, $wrapped, $variants, $driver) {
            foreach ($variants as $variant) {
                if ($driver === 'pgsql') {
                    $like = '%'.addcslashes($variant, '%_\\').'%';
                    $inner->orWhere($column, 'ilike', $like);

                    continue;
                }

                foreach (self::likePatternsForUnicode($variant) as $pattern) {
                    $inner->orWhereRaw($wrapped.' LIKE ?', [$pattern]);
                }
            }
        });
    }

    /**
     * SQLite / MySQL case-folding without relying on LOWER() for Unicode (SQLite LOWER() is ASCII-only).
     *
     * @return list<string>
     */
    private static function likePatternsForUnicode(string $variant): array
    {
        $escaped = static fn (string $v): string => '%'.addcslashes($v, '%_\\').'%';

        $unique = [];
        foreach (
            array_unique([
                $variant,
                mb_strtolower($variant),
                mb_strtoupper($variant),
                mb_convert_case($variant, MB_CASE_TITLE, 'UTF-8'),
            ]) as $v
        ) {
            if ($v !== '') {
                $unique[$escaped($v)] = true;
            }
        }

        return array_keys($unique);
    }
}
