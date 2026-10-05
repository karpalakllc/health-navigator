<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use App\Policies\Support\PrivilegeHierarchy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StaffUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            // Options are narrowed to what the acting user may grant, and the
            // rules re-check server-side: a non-administrator must not be able to
            // hand out the admin column, the Administrator role, or any role
            // carrying permissions they lack (see PrivilegeHierarchy).
            Select::make('role')
                ->options(fn (): array => collect(UserRole::cases())
                    ->filter(fn (UserRole $role) => $role->isStaff())
                    ->filter(fn (UserRole $role) => PrivilegeHierarchy::canGrantUserRoleColumn(self::actor(), $role))
                    ->mapWithKeys(fn (UserRole $role) => [$role->value => ucfirst($role->value)])
                    ->all())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $role = $value instanceof UserRole ? $value : UserRole::tryFrom((string) $value);

                    if ($role === null || ! PrivilegeHierarchy::canGrantUserRoleColumn(self::actor(), $role)) {
                        $fail('You cannot assign this role.');
                    }
                })
                ->required(),
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
                ->label('Permission roles'),
            TextInput::make('password')
                ->password()
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

    public static function afterCreate(UserKind $kind = UserKind::Staff): void
    {
        // handled in CreateStaffUser page
    }
}
