<?php

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Subsequent deploys only run migrations (infra/deploy.md step 7), so the
 * permissions for the report queue and official review responses are created
 * here and given to the built-in roles that hold them by default
 * (PermissionCatalog). Additive only: a role an administrator has trimmed is
 * not otherwise touched, and a role that does not exist yet is left for the
 * seeder to create with its defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::reportsAndResponses() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ([RoleCatalog::ADMINISTRATOR, RoleCatalog::MODERATOR] as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            $defaults = array_intersect(RoleCatalog::defaults()[$name], PermissionCatalog::reportsAndResponses());
            $role->givePermissionTo(array_values($defaults));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', PermissionCatalog::reportsAndResponses())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
