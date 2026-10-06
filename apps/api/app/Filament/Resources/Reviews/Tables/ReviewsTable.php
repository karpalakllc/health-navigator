<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Enums\ReviewStatus;
use App\Filament\Support\ModerationBulkActions;
use App\Filament\Support\ModerationTableColumns;
use App\Filament\Support\ReviewableLabel;
use App\Filament\Support\ReviewResponseActions;
use App\Models\Review;
use App\Support\ReviewBurstDetector;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('burst_flagged_at')
                    ->label('Burst')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (): string => ReviewBurstDetector::THRESHOLD.'+ in '.ReviewBurstDetector::WINDOW_HOURS.' h')
                    ->tooltip('This profile received '.ReviewBurstDetector::THRESHOLD.' or more reviews within '.ReviewBurstDetector::WINDOW_HOURS.' hours. A signal for a closer look; nothing happens automatically.')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resubmitted_at')
                    ->label('Resent')
                    ->dateTime()
                    ->placeholder('—')
                    ->tooltip('Edited and resent by the author after a refusal (once only). A second refusal is final.')
                    ->sortable()
                    ->toggleable(),
            ])
            // A resent review queues by when it came back, not by when it
            // was first written (which would sink it below newer ones).
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('coalesce(resubmitted_at, created_at) desc')
                ->orderByDesc('id'))
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'reviewable']))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(
                        fn (ReviewStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(ReviewStatus::Pending->value),
                Filter::make('burst')
                    ->label('Review bursts ('.ReviewBurstDetector::THRESHOLD.'+ on one profile within '.ReviewBurstDetector::WINDOW_HOURS.' h)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('burst_flagged_at')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending
                        && auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => $record->approve(auth()->user())),
                Action::make('reject')
                    ->visible(fn (Review $record): bool => $record->status === ReviewStatus::Pending
                        && auth()->user()?->can('update', $record))
                    ->form(ModerationBulkActions::rejectionNoteFields())
                    ->requiresConfirmation()
                    ->action(fn (Review $record, array $data) => $record->reject(
                        auth()->user(),
                        $data['rejection_note'] ?? null,
                    )),
                ReviewResponseActions::respond(),
                ReviewResponseActions::remove(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(ModerationBulkActions::forReviews()),
            ]);
    }
}
