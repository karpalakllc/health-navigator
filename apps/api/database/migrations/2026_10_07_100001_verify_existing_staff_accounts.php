<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff accounts are created by an administrator or by platform:bootstrap, never
 * through the public sign-up, so nobody was ever going to click a verification
 * link for them. Left unverified they were the one kind of account that the
 * sign-up flow still treated as "pending" — which is what let a public
 * registration overwrite an administrator's password. Every staff account is
 * verified from creation now; this brings the existing ones into line.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('user_kind', 'staff')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    /**
     * Not reversible: un-verifying staff would reopen exactly what this closes.
     */
    public function down(): void
    {
        //
    }
};
