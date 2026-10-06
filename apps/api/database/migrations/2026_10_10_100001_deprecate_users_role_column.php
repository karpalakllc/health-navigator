<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `users.role` is deprecated: nothing reads or writes it any more (Spatie roles
 * are the authorization source, and the API's `role` field is derived from
 * them — see User::accountRole()). New accounts leave it null.
 *
 * Kept for one release so the previous move can be checked against it, then
 * dropped by a follow-up migration (`$table->dropColumn('role')`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->nullable()->default(null)->change();
        });
    }

    /**
     * Accounts created since have no value; give them the one the old code
     * would have stored, from their Spatie role and kind, before restoring
     * NOT NULL.
     */
    public function down(): void
    {
        $administrators = DB::table(config('permission.table_names.model_has_roles').' as pivot')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'pivot.role_id')
            ->where('roles.name', 'Administrator')
            ->where('pivot.model_type', (new User)->getMorphClass())
            ->select('pivot.model_id');

        DB::table('users')->whereNull('role')->whereIn('id', $administrators)->update(['role' => 'admin']);
        DB::table('users')->whereNull('role')->where('user_kind', 'staff')->update(['role' => 'moderator']);
        DB::table('users')->whereNull('role')->update(['role' => 'member']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('member')->change();
        });
    }
};
