<?php

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the data-import permissions and gives them to the built-in roles
 * that hold them by default (the Administrator). Additive, like
 * 2026_10_14_110006.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::imports() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(RoleCatalog::defaults()) as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            $defaults = array_intersect(RoleCatalog::defaults()[$name], PermissionCatalog::imports());

            if ($defaults !== []) {
                $role->givePermissionTo(array_values($defaults));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', PermissionCatalog::imports())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
