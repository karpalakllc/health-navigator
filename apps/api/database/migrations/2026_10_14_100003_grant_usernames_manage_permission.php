<?php

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * usernames.manage (the blocked/reserved username lists and renaming a member)
 * for the Administrator role, as PermissionCatalog defaults it. Additive
 * only, like 2026_10_13_120002.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::usernames() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::query()->where('name', RoleCatalog::ADMINISTRATOR)->where('guard_name', 'web')->first();

        $role?->givePermissionTo(PermissionCatalog::usernames());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', PermissionCatalog::usernames())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
