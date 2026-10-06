<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Suspend / unsuspend a client account (D7). Authorised by UserPolicy::suspend
 * (`clients.suspend` + the privilege hierarchy). Suspension refuses sign-in and
 * every existing API token from the next request on (AppServiceProvider); the
 * tokens are kept so that lifting it restores the member's sessions. Reviews and
 * forum content are not touched — moderate them separately if needed.
 */
final class SuspendAccountActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record): bool => ! $record->isSuspended()
                && (auth()->user()?->can('suspend', $record) ?? false))
            ->modalHeading('Suspend this account?')
            ->modalDescription('The member is signed out everywhere and cannot sign in until the suspension is lifted. Their reviews and forum posts stay as they are. The reason is visible to staff only.')
            ->modalSubmitActionLabel('Suspend account')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (staff only)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, User $record): void {
                $actor = auth()->user();

                // Re-checked here: visibility is evaluated when the page renders.
                abort_unless($actor instanceof User && $actor->can('suspend', $record), 403);

                $record->suspend($actor, trim((string) $data['reason']));

                // Audit log: who suspended whom, and when. The reason stays
                // on the account only (staff-only text about a member).
                activity('accounts')
                    ->performedOn($record)
                    ->causedBy($actor)
                    ->event('suspended')
                    ->log('suspended');

                Notification::make()
                    ->title('Account suspended')
                    ->success()
                    ->send();
            });
    }

    public static function unsuspend(): Action
    {
        return Action::make('unsuspend')
            ->label('Lift suspension')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (User $record): bool => $record->isSuspended()
                && (auth()->user()?->can('suspend', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Lift the suspension?')
            ->modalDescription('The member can sign in again, and devices that were signed in before the suspension work again until their sessions expire.')
            ->action(function (User $record): void {
                $actor = auth()->user();

                abort_unless($actor instanceof User && $actor->can('suspend', $record), 403);

                $record->unsuspend();

                activity('accounts')
                    ->performedOn($record)
                    ->causedBy($actor)
                    ->event('unsuspended')
                    ->log('unsuspended');

                Notification::make()
                    ->title('Suspension lifted')
                    ->success()
                    ->send();
            });
    }
}
