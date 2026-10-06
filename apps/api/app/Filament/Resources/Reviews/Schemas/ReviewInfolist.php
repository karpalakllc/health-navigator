<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Enums\RemovalCategory;
use App\Enums\ReviewResponseSource;
use App\Enums\ReviewResponseStatus;
use App\Enums\ReviewStatus;
use App\Filament\Support\ReviewableLabel;
use App\Models\Review;
use App\Support\ReviewBurstDetector;
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
                TextEntry::make('response_body')
                    ->label('Official response')
                    ->visible(fn (Review $record): bool => $record->hasResponse())
                    ->columnSpanFull(),
                TextEntry::make('responseBy.name')
                    ->label('Response entered by')
                    ->visible(fn (Review $record): bool => $record->hasResponse()),
                TextEntry::make('response_source')
                    ->label('Response written by')
                    ->formatStateUsing(fn (?ReviewResponseSource $state): string => $state === ReviewResponseSource::Doctor ? 'The doctor (linked account)' : 'Staff')
                    ->visible(fn (Review $record): bool => $record->hasResponse()),
                TextEntry::make('response_status')
                    ->label('Response status')
                    ->badge()
                    ->formatStateUsing(fn (?ReviewResponseStatus $state): string => ucfirst($state->value ?? 'approved'))
                    ->visible(fn (Review $record): bool => $record->hasResponse()),
                TextEntry::make('response_rejection_note')
                    ->label('Reply rejection reason')
                    ->visible(fn (Review $record): bool => filled($record->response_rejection_note))
                    ->columnSpanFull(),
                TextEntry::make('response_at')
                    ->label('Response date')
                    ->dateTime()
                    ->visible(fn (Review $record): bool => $record->hasResponse()),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('published_at')
                    ->dateTime(),
                TextEntry::make('moderated_at')
                    ->dateTime(),
                TextEntry::make('removed_at')
                    ->label('Removed after publication')
                    ->helperText('The public list shows a placeholder with this date and the public reason.')
                    ->dateTime()
                    ->visible(fn (Review $record): bool => $record->removed_at !== null),
                TextEntry::make('removal_category')
                    ->label('Public reason')
                    ->formatStateUsing(fn (?RemovalCategory $state): string => $state?->label() ?? '—')
                    ->visible(fn (Review $record): bool => $record->removed_at !== null),
                TextEntry::make('burst_flagged_at')
                    ->label('Review burst')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (): string => 'Profile received '.ReviewBurstDetector::THRESHOLD.'+ reviews within '.ReviewBurstDetector::WINDOW_HOURS.' h')
                    ->helperText('A signal only: nothing was changed automatically.')
                    ->visible(fn (Review $record): bool => $record->burst_flagged_at !== null),
            ]);
    }
}
