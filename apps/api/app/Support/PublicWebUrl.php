<?php

namespace App\Support;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

final class PublicWebUrl
{
    public static function configured(): bool
    {
        $base = config('zdravje.web_public_url');

        return is_string($base) && $base !== '';
    }

    public static function forRecord(Model $record): ?string
    {
        if (! self::configured()) {
            return null;
        }

        return match (true) {
            $record instanceof Doctor => self::path("/doctors/{$record->slug}"),
            $record instanceof Facility => self::forFacility($record),
            $record instanceof Product => self::path("/products/{$record->slug}"),
            default => null,
        };
    }

    public static function forFacility(Facility $facility): ?string
    {
        if ($facility->type === FacilityType::Pharmacy) {
            return self::path("/pharmacies/{$facility->slug}");
        }

        return self::path("/facilities/{$facility->slug}");
    }

    public static function guidance(): ?string
    {
        return self::path('/guidance');
    }

    private static function path(string $path): ?string
    {
        if (! self::configured()) {
            return null;
        }

        return rtrim((string) config('zdravje.web_public_url'), '/').$path;
    }
}
