<?php

namespace App\Filament\Resources\ForumTopics\Tables;

use App\Enums\ForumContentStatus;
use App\Filament\Support\ModerationBulkActions;
use App\Filament\Support\ModerationTableColumns;
use App\Models\ForumTopic;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ForumTopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ModerationTableColumns::forumStatus(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(48)
                    ->wrap(),
                ModerationTableColumns::bodyExcerpt(),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('user.name')
                    ->label('Author'),
                IconColumn::make('is_pinned')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_locked')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'category']))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ForumContentStatus::cases())->mapWithKeys(
                        fn (ForumContentStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(ForumContentStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->visible(fn (ForumTopic $record): bool => $record->status === ForumContentStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (ForumTopic $record) => $record->approve(auth()->user())),
                Action::make('reject')
                    ->visible(fn (ForumTopic $record): bool => $record->status === ForumContentStatus::Pending)
                    ->form([
                        Textarea::make('rejection_note')->label('Rejection note (internal)')->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (ForumTopic $record, array $data) => $record->reject(
                        auth()->user(),
                        $data['rejection_note'] ?? null,
                    )),
                Action::make('pin')
                    ->label(fn (ForumTopic $record): string => $record->is_pinned ? 'Unpin' : 'Pin')
                    ->visible(fn (ForumTopic $record): bool => $record->status === ForumContentStatus::Approved)
                    ->action(fn (ForumTopic $record) => $record->update(['is_pinned' => ! $record->is_pinned])),
                Action::make('lock')
                    ->label(fn (ForumTopic $record): string => $record->is_locked ? 'Unlock' : 'Lock')
                    ->visible(fn (ForumTopic $record): bool => $record->status === ForumContentStatus::Approved)
                    ->action(fn (ForumTopic $record) => $record->update(['is_locked' => ! $record->is_locked])),
            ])
            ->toolbarActions([
                BulkActionGroup::make(ModerationBulkActions::forForumTopics()),
            ]);
    }
}
