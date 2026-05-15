<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class FormatsDates
{
    public static function toIso8601(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
