<?php

namespace App\Filament\Support;

use App\Actions\ChangeUsername;
use App\Models\User;
use App\Support\Usernames\TemporaryUsername;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Staff rename an account's username — typically an offensive or
 * impersonating name that got past the lists. Needs `usernames.manage` and
 * the right to edit the account (UserPolicy, so the privilege hierarchy
 * applies). The old name is kept in the username history with the reason and
 * who renamed it, and stays reserved for six months. Left empty, the account
 * gets a temporary name and the member chooses a new one at the next sign-in.
 */
final class RenameUsernameAction
{
    public static function make(): Action
    {
        return Action::make('renameUsername')
            ->label('Rename username')
            ->icon('heroicon-o-identification')
            ->visible(fn (User $record): bool => ! $record->isAnonymised() && self::allowed($record))
            ->modalHeading('Rename this username?')
            ->modalDescription('The current name is reserved for 6 months so nobody else can take it. The member is not emailed.')
            ->modalSubmitActionLabel('Rename')
            ->schema([
                TextInput::make('username')
                    ->label('New username')
                    ->helperText('Leave empty to give a temporary name and let the member choose a new one at the next sign-in.')
                    ->rule(fn (User $record) => UsernameField::rule($record)),
                Textarea::make('reason')
                    ->label('Reason (staff only)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(500),
            ])
            ->action(function (array $data, User $record): void {
                $actor = auth()->user();

                // Re-checked here: visibility is evaluated when the page renders.
                abort_unless($actor instanceof User && self::allowed($record), 403);

                $username = is_string($data['username'] ?? null) && trim($data['username']) !== '' ? $data['username'] : null;

                app(ChangeUsername::class)->handle($record, $username, $actor, trim((string) $data['reason']));

                Notification::make()
                    ->title(TemporaryUsername::isTemporary($record->username)
                        ? 'Username replaced; the member will choose a new one'
                        : 'Username renamed')
                    ->success()
                    ->send();
            });
    }

    private static function allowed(User $record): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->can('usernames.manage')
            && $actor->can('update', $record);
    }
}
