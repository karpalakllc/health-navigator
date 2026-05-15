<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class UniqueSlug
{
    /**
     * @param  Builder<Model>  $query
     */
    public static function forQuery(Builder $query, string $baseSlug, string $column = 'slug'): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($query->clone()->where($column, $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
