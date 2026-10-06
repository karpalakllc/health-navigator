<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permission catalogue and the built-in roles with their default
 * permissions. It never touches who holds which role: assignments are made by
 * registration, the admin panel, platform:bootstrap and the demo seeders, and
 * re-running this must not undo a manual promotion or demotion. (It used to
 * re-sync every account from the legacy `users.role` column on each run.)
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleCatalog::defaults() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
