<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the legacy `users.role` column, deprecated (nullable, unread, unwritten)
 * for one release by 2026_10_10_100001. Spatie roles are the only authorization
 * source; the API's `role` field is derived from them (User::accountRole()).
 *
 * Reversible: down() restores the column as the deprecation migration left it
 * (nullable, no default) and fills it with the value the old code would have
 * stored, so rolling further back through 2026_10_10_100001 still works.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->nullable()->after('email');
        });

        $administrators = DB::table(config('permission.table_names.model_has_roles').' as pivot')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'pivot.role_id')
            ->where('roles.name', 'Administrator')
            ->where('pivot.model_type', (new User)->getMorphClass())
            ->select('pivot.model_id');

        DB::table('users')->whereIn('id', $administrators)->update(['role' => 'admin']);
        DB::table('users')->whereNull('role')->where('user_kind', 'staff')->update(['role' => 'moderator']);
        DB::table('users')->whereNull('role')->update(['role' => 'member']);
    }
};
