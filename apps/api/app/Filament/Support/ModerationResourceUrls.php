<?php

namespace App\Filament\Support;

use Filament\Resources\Resource;

final class ModerationResourceUrls
{
    /**
     * @param  class-string<resource>  $resource
     */
    public static function indexPending(string $resource, string $filterName, string $pendingValue): string
    {
        return $resource::getUrl('index', [
            'tableFilters' => [
                $filterName => ['value' => $pendingValue],
            ],
        ]);
    }
}
