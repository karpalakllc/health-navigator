<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Filament\Support\EmailField;
use App\Filament\Support\UsernameField;
use App\Models\User;
use App\Policies\Support\PrivilegeHierarchy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class StaffUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->helperText('Private: shown here and on the account page, never publicly.'),
            UsernameField::make(),
            EmailField::make()->required()->unique(ignoreRecord: true),
            // Options are narrowed to what the acting user may grant, and the
            // rule re-checks server-side: a non-administrator must not be able to
            // hand out the Administrator role, or any role carrying permissions
            // they lack (see PrivilegeHierarchy). These roles are the account's
            // whole authorization; there is no separate account-type column.
            Select::make('roles')
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->whereKey(
                        PrivilegeHierarchy::grantableRoles(self::actor())->modelKeys(),
                    ),
                )
                ->multiple()
                ->preload()
                ->options(fn (): array => PrivilegeHierarchy::grantableRoles(self::actor())->pluck('name', 'id')->all())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! PrivilegeHierarchy::canGrantRoleIds(self::actor(), (array) $value)) {
                        $fail('You cannot assign a role with permissions you do not hold.');
                    }
                })
                ->required()
                ->label('Permission roles'),
            TextInput::make('password')
                ->password()
                ->rule(Password::defaults())
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
        ]);
    }

    private static function actor(): User
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
