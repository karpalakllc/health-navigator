<?php

namespace App\Filament\Resources\ForumTopics\Schemas;

use App\Enums\ForumContentStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ForumTopicInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ForumContentStatus $state): string => ucfirst($state->value))
                    ->color(fn (ForumContentStatus $state): string => match ($state) {
                        ForumContentStatus::Pending => 'warning',
                        ForumContentStatus::Approved => 'success',
                        ForumContentStatus::Rejected => 'danger',
                    }),
                TextEntry::make('title'),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('user.name')->label('Author'),
                TextEntry::make('body')->columnSpanFull(),
                TextEntry::make('rejection_note')
                    ->visible(fn ($record): bool => filled($record->rejection_note))
                    ->columnSpanFull(),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
