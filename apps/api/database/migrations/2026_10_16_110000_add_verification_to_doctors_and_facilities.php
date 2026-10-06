<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Верифициран“ / „Неверифициран“ on doctor, facility and pharmacy profiles
 * (pharmacies are facilities with type pharmacy). Written only through
 * App\Support\Verification\VerificationWriter.
 *
 * - verified_at: set while the profile is verified, null otherwise (the
 *   public status and the „Само верифицирани“ filter read only this).
 * - verification_basis: why it is verified (VerificationBasis); the public
 *   label is derived from it. Cleared when the profile is unverified.
 * - verification_reasons: internal evidence / reasons (never in the API).
 * - verification_source: 'auto' (the import engine) or 'staff'. A staff
 *   decision is not overridden by later automatic runs.
 * - verified_by_id: the staff member behind the last staff decision.
 * - verification_checked_at: when the writer last evaluated the profile.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['doctors', 'facilities'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('verified_at')->nullable()->index();
                $blueprint->string('verification_basis', 32)->nullable();
                $blueprint->json('verification_reasons')->nullable();
                $blueprint->string('verification_source', 8)->nullable();
                $blueprint->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->timestamp('verification_checked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('verified_by_id');
                $blueprint->dropIndex(['verified_at']);
                $blueprint->dropColumn(['verified_at', 'verification_basis', 'verification_reasons', 'verification_source', 'verification_checked_at']);
            });
        }
    }
};
