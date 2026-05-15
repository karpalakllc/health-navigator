<?php

namespace App\Filament\Tables\Filters;

use Filament\Tables\Filters\SelectFilter;

final class PublicationStatusFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('is_published')
            ->label('Publication')
            ->options([
                '1' => 'Published',
                '0' => 'Draft',
            ]);
    }
}
