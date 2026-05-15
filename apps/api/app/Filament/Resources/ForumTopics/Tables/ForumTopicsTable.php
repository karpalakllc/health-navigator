<?php

namespace App\Filament\Resources\ForumTopics\Tables;

use App\Enums\ForumContentStatus;
use App\Filament\Support\ModerationBulkActions;
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
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('user.name')->label('Author'),
                IconColumn::make('is_pinned')->boolean(),
                IconColumn::make('is_locked')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
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
