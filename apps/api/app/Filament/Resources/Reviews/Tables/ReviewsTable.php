<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Enums\ReviewStatus;
use App\Filament\Support\ModerationBulkActions;
use App\Filament\Support\ModerationTableColumns;
use App\Filament\Support\ReviewableLabel;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ModerationTableColumns::reviewStatus(),
                TextColumn::make('rating')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('reviewable_label')
                    ->label('Target')
                    ->state(fn (Review $record): string => ReviewableLabel::forReview($record))
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),
                ModerationTableColumns::bodyExcerpt(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'reviewable']))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(
                        fn (ReviewStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(ReviewStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => $record->approve(auth()->user())),
                Action::make('reject')
                    ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending)
                    ->form([
                        Textarea::make('rejection_note')
                            ->label('Rejection note (internal)')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (Review $record, array $data) => $record->reject(
                        auth()->user(),
                        $data['rejection_note'] ?? null,
                    )),
            ])
            ->toolbarActions([
                BulkActionGroup::make(ModerationBulkActions::forReviews()),
            ]);
    }
}
