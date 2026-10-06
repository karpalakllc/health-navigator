<?php

use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Account suspension (D7). A suspended account cannot sign in and its existing
 * API tokens are refused (not deleted) until it is unsuspended; its content is
 * left alone. The reason is staff-only and never shown to the member.
 *
 * The new `clients.suspend` permission is given to an existing Administrator
 * role here, because RoleCatalog::ensure() leaves existing roles untouched and
 * the roles seeder is not re-run on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->foreignId('suspended_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        $roles = config('permission.table_names.roles', 'roles');
        $permissions = config('permission.table_names.permissions', 'permissions');
        $roleHasPermissions = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        $administrator = DB::table($roles)
            ->where('name', RoleCatalog::ADMINISTRATOR)
            ->where('guard_name', 'web')
            ->value('id');

        if ($administrator === null) {
            return;
        }

        $permission = DB::table($permissions)->where('name', 'clients.suspend')->where('guard_name', 'web')->value('id')
            ?? DB::table($permissions)->insertGetId([
                'name' => 'clients.suspend',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table($roleHasPermissions)->insertOrIgnore([
            'permission_id' => $permission,
            'role_id' => $administrator,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by_id');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });

        DB::table(config('permission.table_names.permissions', 'permissions'))
            ->where('name', 'clients.suspend')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
