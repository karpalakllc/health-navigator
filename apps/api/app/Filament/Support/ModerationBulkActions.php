<?php

namespace App\Filament\Support;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

final class ModerationBulkActions
{
    /**
     * @return array<BulkAction>
     */
    public static function forReviews(): array
    {
        return [
            self::approve('Approve selected', function (Review $record): void {
                if ($record->status !== ReviewStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                $record->approve(auth()->user());
            }),
            self::reject('Reject selected', function (Review $record, ?string $note): void {
                if ($record->status !== ReviewStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                $record->reject(auth()->user(), $note);
            }),
        ];
    }

    /**
     * @return array<BulkAction>
     */
    public static function forForumTopics(): array
    {
        return [
            self::approve('Approve selected', function (ForumTopic $record): void {
                if ($record->status !== ForumContentStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                $record->approve(auth()->user());
            }),
            self::reject('Reject selected', function (ForumTopic $record, ?string $note): void {
                if ($record->status !== ForumContentStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                $record->reject(auth()->user(), $note);
            }),
        ];
    }

    /**
     * @return array<BulkAction>
     */
    public static function forForumPosts(): array
    {
        return [
            self::approve('Approve selected', function (ForumPost $record): void {
                if ($record->status !== ForumContentStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                // Topic counters are updated by ForumPost::afterApproved().
                $record->approve(auth()->user());
            }),
            self::reject('Reject selected', function (ForumPost $record, ?string $note): void {
                if ($record->status !== ForumContentStatus::Pending) {
                    return;
                }

                if (! auth()->user()?->can('update', $record)) {
                    return;
                }

                $record->reject(auth()->user(), $note);
            }),
        ];
    }

    /**
     * @param  Closure(Model): void  $approve
     */
    private static function approve(string $label, Closure $approve): BulkAction
    {
        return BulkAction::make('approve_selected')
            ->label($label)
            ->requiresConfirmation()
            ->action(function (EloquentCollection $records) use ($approve): void {
                $records->each($approve);

                Notification::make()
                    ->title('Selected items approved')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  Closure(Model, ?string): void  $reject
     */
    private static function reject(string $label, Closure $reject): BulkAction
    {
        return BulkAction::make('reject_selected')
            ->label($label)
            ->requiresConfirmation()
            ->form([
                Textarea::make('rejection_note')
                    ->label('Rejection note (internal, optional)')
                    ->rows(3),
            ])
            ->action(function (EloquentCollection $records, array $data) use ($reject): void {
                $note = $data['rejection_note'] ?? null;

                $records->each(fn (Model $record) => $reject($record, $note));

                Notification::make()
                    ->title('Selected items rejected')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
