<?php

namespace App\Filament\Support;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
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
     * Reasons a moderator can pick instead of typing one. Written to the
     * author, in Macedonian; the first is the default.
     *
     * @var list<string>
     */
    public const PRESET_REASONS = [
        'Содржината не е во согласност со правилата на заедницата.',
        'Содржи лични или здравствени податоци за друго лице.',
        'Содржи навреди, закани или вознемирување.',
        'Изнесува обвинувања како факти.',
        'Содржи реклама или врски кон други страници.',
        'Не се однесува на профилот или на темата.',
    ];

    /**
     * The reason given to the author. The terms promise authors the reason a
     * review is refused or a post removed, so it is required: prefilled with
     * a general reason and replaceable from a list or by typing.
     *
     * It is not internal: it goes into the rejection email and is returned to
     * the author by the My* API resources, so the label must say so.
     *
     * @return array{Select, Textarea}
     */
    public static function rejectionNoteFields(string $name = 'rejection_note', ?string $default = null): array
    {
        return [
            Select::make($name.'_preset')
                ->label('Common reasons')
                ->options(array_combine(self::PRESET_REASONS, self::PRESET_REASONS))
                ->placeholder('Pick one to fill in the reason below')
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, ?string $state) use ($name): void {
                    if (filled($state)) {
                        $set($name, $state);
                    }
                }),
            Textarea::make($name)
                ->label('Reason (shown to the author)')
                ->helperText('Sent to the author in the email and shown in their account. Write it in Macedonian, without naming whoever reported it.')
                ->default($default ?? self::PRESET_REASONS[0])
                ->required()
                ->maxLength(500)
                ->rows(3),
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
            ->form(self::rejectionNoteFields())
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
