<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Enums\ReviewStatus;
use App\Filament\Support\ReviewableLabel;
use App\Models\Review;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReviewStatus $state): string => ucfirst($state->value))
                    ->color(fn (ReviewStatus $state): string => match ($state) {
                        ReviewStatus::Pending => 'warning',
                        ReviewStatus::Approved => 'success',
                        ReviewStatus::Rejected => 'danger',
                    }),
                TextEntry::make('rating'),
                TextEntry::make('body')
                    ->columnSpanFull(),
                TextEntry::make('user.name')
                    ->label('Author'),
                TextEntry::make('user.email')
                    ->label('Author email'),
                TextEntry::make('reviewable_label')
                    ->label('Target')
                    ->state(fn (Review $record): string => ReviewableLabel::forReviewDetail($record)),
                TextEntry::make('rejection_note')
                    ->visible(fn (Review $record): bool => filled($record->rejection_note))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('published_at')
                    ->dateTime(),
                TextEntry::make('moderated_at')
                    ->dateTime(),
            ]);
    }
}
