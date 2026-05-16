<?php

namespace App\Support;

final class FrontendUrl
{
    public static function base(): string
    {
        return rtrim((string) config('zdravje.frontend_url'), '/');
    }

    public static function to(string $path): string
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return self::base().$path;
    }
}
