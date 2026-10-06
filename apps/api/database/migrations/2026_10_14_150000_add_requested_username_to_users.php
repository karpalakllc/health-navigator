<?php

use App\Support\Usernames\TemporaryUsername;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The username a sign-up asked for, held privately until the address is
 * verified. An unverified account carries a temporary „clen-…“ name instead:
 * if it held the requested name, the availability check would answer
 * differently for a new address (row created, name held) and an existing one
 * (no row) — an account-existence oracle by e-mail. Verification gives the
 * account its requested name if nobody took it meanwhile, or asks the member
 * to choose (AssignRequestedUsername).
 *
 * Unverified accounts created before this held their name in `username`; they
 * are moved over the same way, so they stop holding it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('requested_username', 30)->nullable()->after('username_skeleton');
        });

        DB::table('users')
            ->where('user_kind', 'client')
            ->whereNull('email_verified_at')
            ->whereNull('anonymised_at')
            ->where('must_choose_username', false)
            ->whereNotNull('username')
            ->select(['id', 'username'])
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $temporary = TemporaryUsername::generate();

                    DB::table('users')->where('id', $user->id)->update([
                        'requested_username' => $user->username,
                        'username' => $temporary,
                        'username_normalized' => UsernameNormalizer::key($temporary),
                        'username_skeleton' => UsernameNormalizer::skeleton($temporary),
                        'must_choose_username' => true,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('requested_username');
        });
    }
};
