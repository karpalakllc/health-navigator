<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Slug
{
    public static function fromName(string $name, ?string $fallback = null): string
    {
        $slug = Str::slug($name);

        if ($slug !== '') {
            return $slug;
        }

        return Str::slug($fallback ?? 'item') ?: 'item';
    }
}
