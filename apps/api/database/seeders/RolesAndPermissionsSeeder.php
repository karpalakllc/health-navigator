<?php

namespace Database\Seeders;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $administrator = Role::findOrCreate('Administrator', 'web');
        $administrator->syncPermissions(PermissionCatalog::all());

        $moderator = Role::findOrCreate('Moderator', 'web');
        $moderator->syncPermissions(PermissionCatalog::moderatorDefaults());

        $forumModerator = Role::findOrCreate('Forum Moderator', 'web');
        $forumModerator->syncPermissions(PermissionCatalog::forumModeratorDefaults());

        User::query()
            ->where('role', UserRole::Admin)
            ->each(fn (User $user) => $user->syncRoles([$administrator]));

        User::query()
            ->where('role', UserRole::Moderator)
            ->each(fn (User $user) => $user->syncRoles([$moderator]));

        User::query()
            ->where('user_kind', UserKind::Staff)
            ->whereDoesntHave('roles')
            ->each(function (User $user): void {
                $user->assignRole(
                    $user->role === UserRole::Admin ? 'Administrator' : 'Moderator',
                );
            });
    }
}
