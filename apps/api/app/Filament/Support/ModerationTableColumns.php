<?php

namespace App\Filament\Support;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use Filament\Tables\Columns\TextColumn;

final class ModerationTableColumns
{
    public static function reviewStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->badge()
            ->formatStateUsing(fn (ReviewStatus $state): string => ucfirst($state->value))
            ->color(fn (ReviewStatus $state): string => match ($state) {
                ReviewStatus::Pending => 'warning',
                ReviewStatus::Approved => 'success',
                ReviewStatus::Rejected => 'danger',
            })
            ->sortable();
    }

    public static function forumStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->badge()
            ->formatStateUsing(fn (ForumContentStatus $state): string => ucfirst($state->value))
            ->color(fn (ForumContentStatus $state): string => match ($state) {
                ForumContentStatus::Pending => 'warning',
                ForumContentStatus::Approved => 'success',
                ForumContentStatus::Rejected => 'danger',
            })
            ->sortable();
    }

    public static function bodyExcerpt(string $column = 'body', int $limit = 72): TextColumn
    {
        return TextColumn::make($column)
            ->label('Excerpt')
            ->limit($limit)
            ->wrap()
            ->toggleable();
    }
}
