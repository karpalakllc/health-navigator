<?php

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the doctor-account and audit-log permissions and gives them to the
 * built-in roles that hold them by default (the Administrator). Additive, like
 * 2026_10_13_120002: a trimmed role is not otherwise touched, and a missing
 * role is left for the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::doctorAccountsAndAudit() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(RoleCatalog::defaults()) as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            $defaults = array_intersect(RoleCatalog::defaults()[$name], PermissionCatalog::doctorAccountsAndAudit());

            if ($defaults !== []) {
                $role->givePermissionTo(array_values($defaults));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', PermissionCatalog::doctorAccountsAndAudit())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
