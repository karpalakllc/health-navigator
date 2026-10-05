<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\User;
use App\Policies\Support\PrivilegeHierarchy;
use App\Support\PermissionCatalog;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            // A non-administrator may only put permissions they hold on a role;
            // RolePolicy already keeps them off their own role and Administrator.
            CheckboxList::make('permissions')
                ->relationship('permissions', 'name')
                ->options(fn (): array => Permission::query()
                    ->whereIn('name', PermissionCatalog::all())
                    ->whereKey(PrivilegeHierarchy::grantablePermissionIds(self::actor()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $grantable = PrivilegeHierarchy::grantablePermissionIds(self::actor());

                    foreach ((array) $value as $permissionId) {
                        if (! in_array((int) $permissionId, $grantable, true)) {
                            $fail('You cannot grant a permission you do not hold.');

                            return;
                        }
                    }
                })
                ->columns(2)
                ->searchable()
                ->bulkToggleable(),
        ]);
    }

    private static function actor(): User
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
