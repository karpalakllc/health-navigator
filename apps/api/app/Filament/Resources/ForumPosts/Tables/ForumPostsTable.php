<?php

namespace App\Filament\Resources\ForumPosts\Tables;

use App\Enums\ForumContentStatus;
use App\Filament\Support\ModerationBulkActions;
use App\Filament\Support\ModerationTableColumns;
use App\Models\ForumPost;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ForumPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ModerationTableColumns::forumStatus(),
                TextColumn::make('topic.title')
                    ->label('Topic')
                    ->limit(36)
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Author'),
                ModerationTableColumns::bodyExcerpt(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'topic']))
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
                    ->visible(fn (ForumPost $record): bool => $record->status === ForumContentStatus::Pending
                        && auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (ForumPost $record): void {
                        // Topic counters are updated by ForumPost::afterApproved().
                        $record->approve(auth()->user());
                    }),
                Action::make('reject')
                    ->visible(fn (ForumPost $record): bool => $record->status === ForumContentStatus::Pending
                        && auth()->user()?->can('update', $record))
                    ->form([
                        Textarea::make('rejection_note')->label('Rejection note (internal)')->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (ForumPost $record, array $data) => $record->reject(
                        auth()->user(),
                        $data['rejection_note'] ?? null,
                    )),
            ])
            ->toolbarActions([
                BulkActionGroup::make(ModerationBulkActions::forForumPosts()),
            ]);
    }
}
