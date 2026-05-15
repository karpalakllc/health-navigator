<?php

namespace App\Filament\Resources\ForumPosts\Tables;

use App\Enums\ForumContentStatus;
use App\Filament\Support\ModerationBulkActions;
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
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('topic.title')->label('Topic')->limit(30),
                TextColumn::make('user.name')->label('Author'),
                TextColumn::make('body')->limit(50),
                TextColumn::make('created_at')->dateTime()->sortable(),
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
                    ->visible(fn (ForumPost $record): bool => $record->status === ForumContentStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (ForumPost $record): void {
                        $record->approve(auth()->user());
                        $topic = $record->topic()->first();
                        if ($topic !== null && $topic->status === ForumContentStatus::Approved) {
                            $topic->recordApprovedReply();
                        }
                    }),
                Action::make('reject')
                    ->visible(fn (ForumPost $record): bool => $record->status === ForumContentStatus::Pending)
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
