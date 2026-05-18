<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

final class DirectoryTableColumns
{
    public static function publicationBadge(): TextColumn
    {
        return TextColumn::make('is_published')
            ->label('Publication')
            ->badge()
            ->formatStateUsing(fn (bool $state): string => $state ? 'Published' : 'Draft')
            ->color(fn (bool $state): string => $state ? 'success' : 'gray')
            ->sortable();
    }

    public static function featuredIcon(): IconColumn
    {
        return IconColumn::make('is_featured')
            ->label('Featured')
            ->boolean()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function sponsoredIcon(): IconColumn
    {
        return IconColumn::make('is_sponsored')
            ->label('Sponsored')
            ->boolean()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function hiddenSlug(): TextColumn
    {
        return TextColumn::make('slug')
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function updatedAt(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function acceptsPatientsIcon(): IconColumn
    {
        return IconColumn::make('accepts_new_patients')
            ->label('New patients')
            ->boolean()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
