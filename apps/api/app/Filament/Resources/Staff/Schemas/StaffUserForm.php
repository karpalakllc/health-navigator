<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\UserKind;
use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class StaffUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')
                ->options(collect(UserRole::cases())
                    ->filter(fn (UserRole $role) => $role->isStaff())
                    ->mapWithKeys(fn (UserRole $role) => [$role->value => ucfirst($role->value)])
                    ->all())
                ->required(),
            Select::make('roles')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->options(Role::query()->orderBy('name')->pluck('name', 'id'))
                ->label('Permission roles'),
            TextInput::make('password')
                ->password()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
        ]);
    }

    public static function afterCreate(UserKind $kind = UserKind::Staff): void
    {
        // handled in CreateStaffUser page
    }
}
