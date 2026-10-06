<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deploy transition for contested registrations.
 *
 * Before registration_contested_at existed, a second sign-up for a pending
 * address replaced its name and password, and nothing recorded that it had
 * happened. Verification links from that code carried a credential= fingerprint
 * and are still validly signed, so one sitting in an inbox would activate
 * whatever password is stored — possibly the one a stranger set.
 *
 * Which pending accounts were re-registered is not knowable after the fact, so
 * every one is treated as contested: verifying it confirms the address and sends
 * a password reset instead of activating the stored password. Conservative — a
 * genuine single registrant just sets their password once more.
 *
 * Scope matches AuthController::notifyExistingAccount(): unverified client
 * accounts holding no role. Staff and role holders were never "pending".
 */
return new class extends Migration
{
    public function up(): void
    {
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $morphType = (new User)->getMorphClass();

        DB::table('users')
            ->where('user_kind', 'client')
            ->whereNull('email_verified_at')
            ->whereNull('registration_contested_at')
            ->whereNotExists(function ($query) use ($modelHasRoles, $morphKey, $morphType): void {
                $query->selectRaw('1')
                    ->from($modelHasRoles)
                    ->whereColumn("{$modelHasRoles}.{$morphKey}", 'users.id')
                    ->where("{$modelHasRoles}.model_type", $morphType);
            })
            ->update(['registration_contested_at' => DB::raw('CURRENT_TIMESTAMP')]);
    }

    /**
     * Not reversible: the flag cannot be told apart from one set by a real
     * second sign-up after deploy.
     */
    public function down(): void
    {
        //
    }
};
