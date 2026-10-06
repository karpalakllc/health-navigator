<?php

namespace App\Filament\Support;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Right of reply (docs/notice-and-action.md): staff attach the official
 * response of the reviewed doctor or facility, which the public profile shows
 * labelled under the review. Only on published reviews.
 */
final class ReviewResponseActions
{
    public static function respond(): Action
    {
        return Action::make('respond')
            ->label(fn (Review $record): string => $record->hasResponse() ? 'Edit response' : 'Add official response')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            // A doctor's own reply is approved or rejected below, not rewritten
            // under staff's name; removing it stays possible.
            ->visible(fn (Review $record): bool => self::allowed($record) && ! $record->hasDoctorReply())
            ->modalDescription('Entered on behalf of the reviewed doctor or facility, after they sent it to us. Shown publicly under the review, signed with the profile name. Plain text, in Macedonian.')
            ->fillForm(fn (Review $record): array => ['response_body' => $record->response_body])
            ->form([
                Textarea::make('response_body')
                    ->label('Official response')
                    ->required()
                    ->minLength(2)
                    ->maxLength(Review::RESPONSE_MAX_LENGTH)
                    ->rows(6),
            ])
            ->action(function (Review $record, array $data): void {
                if (! self::allowed($record) || $record->hasDoctorReply()) {
                    return;
                }

                $body = Review::plainResponse((string) ($data['response_body'] ?? ''));

                if ($body === '') {
                    Notification::make()->title('The response is empty')->danger()->send();

                    return;
                }

                $record->respond(self::actor(), $body);

                Notification::make()->title('Response saved')->success()->send();
            });
    }

    public static function remove(): Action
    {
        return Action::make('removeResponse')
            ->label('Remove response')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Review $record): bool => $record->hasResponse() && self::mayRespond($record))
            ->requiresConfirmation()
            ->action(function (Review $record): void {
                if (! self::mayRespond($record)) {
                    return;
                }

                $record->removeResponse();

                Notification::make()->title('Response removed')->success()->send();
            });
    }

    /**
     * A linked doctor's own reply („Мој профил“) waits here while
     * doctor_replies_require_moderation is on. Approving publishes it under
     * the review with the „Одговор од лекарот“ label.
     */
    public static function approveDoctorReply(): Action
    {
        return Action::make('approveDoctorReply')
            ->label('Approve doctor reply')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Review $record): bool => $record->hasPendingDoctorReply() && self::mayRespond($record))
            ->requiresConfirmation()
            ->modalDescription('Check that the reply does not reveal or confirm anything about a patient (not even that the reviewer was one), then publish it.')
            ->action(function (Review $record): void {
                if (! $record->hasPendingDoctorReply() || ! self::mayRespond($record)) {
                    return;
                }

                $record->approveDoctorReply(self::actor());

                Notification::make()->title('Doctor reply published')->success()->send();
            });
    }

    public static function rejectDoctorReply(): Action
    {
        return Action::make('rejectDoctorReply')
            ->label('Reject doctor reply')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Review $record): bool => $record->hasPendingDoctorReply() && self::mayRespond($record))
            ->modalDescription('The reply stays unpublished. The doctor sees this reason on their dashboard and can rewrite the reply.')
            ->schema([
                Textarea::make('response_rejection_note')
                    ->label('Reason (shown to the doctor)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (Review $record, array $data): void {
                if (! $record->hasPendingDoctorReply() || ! self::mayRespond($record)) {
                    return;
                }

                $record->rejectDoctorReply(self::actor(), trim((string) ($data['response_rejection_note'] ?? '')));

                Notification::make()->title('Doctor reply rejected')->success()->send();
            });
    }

    /** Adding or editing: only where the response would be shown. */
    private static function allowed(Review $record): bool
    {
        return $record->status === ReviewStatus::Approved && self::mayRespond($record);
    }

    /** Removing works on any review, so a hidden one can be cleaned up too. */
    private static function mayRespond(Review $record): bool
    {
        return auth()->user()?->can('respond', $record) ?? false;
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
