<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Admin-initiated password reset for a staff or client account.
 *
 * Extracted from the retired generic Users resource, where it was the only copy
 * — the Staff and Clients resources that superseded it had no equivalent, so
 * deleting that resource outright would have silently removed the documented
 * way for an administrator to set a member's password.
 */
final class SetPasswordAction
{
    public static function make(): Action
    {
        return Action::make('resetPassword')
            ->label('Set password')
            ->icon('heroicon-o-key')
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->form([
                TextInput::make('password')
                    ->password()
                    ->required()
                    // The same rule registration and reset use. Staff-set passwords
                    // were the weakest credentials on the platform at minLength(8)
                    // while everything else moved to ten characters plus a breach check.
                    ->rule(Password::defaults())
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->password()
                    ->required()
                    ->label('Confirm password'),
            ])
            ->action(function (array $data, User $record): void {
                $record->update([
                    'password' => Hash::make($data['password']),
                ]);

                Notification::make()
                    ->title('Password updated')
                    ->body('Share the new password with the member through a secure channel.')
                    ->success()
                    ->send();
            });
    }
}
