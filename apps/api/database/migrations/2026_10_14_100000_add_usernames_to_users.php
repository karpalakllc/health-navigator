<?php

use App\Support\Usernames\TemporaryUsername;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public unique usernames replace the „Име П.“ display name (owner decision
 * 2026-10-14: initials let a doctor identify the reviewer). `display_name`
 * stays for now — nothing reads it any more; a later release drops it.
 *
 * Every existing account gets a temporary username („clen-k3x9p2“) and is
 * asked to choose its own at the next sign-in (`must_choose_username`).
 * Deleted (anonymised) accounts get none: they are shown as „Избришан
 * корисник“.
 *
 * Also the sign-up consent: when the member confirmed „14+ and I accept the
 * terms and the privacy policy“, and which version of the terms that was.
 * Accounts from before have none on record (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('display_name');
            $table->string('username_normalized', 100)->nullable()->after('username');
            $table->string('username_skeleton', 100)->nullable()->after('username_normalized');
            $table->timestamp('username_changed_at')->nullable()->after('username_skeleton');
            $table->boolean('must_choose_username')->default(false)->after('username_changed_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('must_choose_username');
            $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
        });

        $taken = [];

        DB::table('users')
            ->whereNull('username')
            ->whereNull('anonymised_at')
            ->select(['id'])
            ->chunkById(500, function ($users) use (&$taken): void {
                foreach ($users as $user) {
                    do {
                        $username = TemporaryUsername::candidate();
                    } while (isset($taken[$username]));

                    $taken[$username] = true;

                    DB::table('users')->where('id', $user->id)->update([
                        'username' => $username,
                        'username_normalized' => UsernameNormalizer::key($username),
                        'username_skeleton' => UsernameNormalizer::skeleton($username),
                        'must_choose_username' => true,
                    ]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
            $table->unique('username_normalized');
            $table->index('username_skeleton');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['username_normalized']);
            $table->dropIndex(['username_skeleton']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'username_normalized',
                'username_skeleton',
                'username_changed_at',
                'must_choose_username',
                'terms_accepted_at',
                'terms_version',
            ]);
        });
    }
};
