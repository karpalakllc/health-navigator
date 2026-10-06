<?php

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * licences.manage (the Комора licence staging list and the licence specialty
 * mapping) for the Administrator role, as PermissionCatalog defaults it.
 * Additive only, like 2026_10_14_100003.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::licences() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::query()->where('name', RoleCatalog::ADMINISTRATOR)->where('guard_name', 'web')->first();

        $role?->givePermissionTo(PermissionCatalog::licences());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', PermissionCatalog::licences())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
