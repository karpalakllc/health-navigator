<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Set password')
                ->icon('heroicon-o-key')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->form([
                    TextInput::make('password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->confirmed(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'password' => Hash::make($data['password']),
                    ]);

                    Notification::make()
                        ->title('Password updated')
                        ->body('Share the new password with the member through a secure channel.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
