<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Login now refuses accounts with a null email_verified_at.
 *
 * Nothing ever sent those accounts a verification link — the old
 * `require_email_verification` toggle only nulled the column and stopped there —
 * so anyone registered while it was on would be locked out with no explanation.
 * Recovery exists (resend), but expecting a user to discover it is not a plan.
 *
 * Accounts created before this deploy are therefore accepted as-is. Verification
 * applies from here on, where a link is actually sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        $backfilled = DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);

        if ($backfilled > 0) {
            // Visible in the migration output rather than silent, so an operator
            // knows accounts were grandfathered in.
            echo "  Grandfathered {$backfilled} pre-existing account(s) past email verification.".PHP_EOL;
        }
    }

    /**
     * Not reversible: which accounts were grandfathered is not recorded, and
     * un-verifying them would lock out exactly the users this protects.
     */
    public function down(): void
    {
        //
    }
};
