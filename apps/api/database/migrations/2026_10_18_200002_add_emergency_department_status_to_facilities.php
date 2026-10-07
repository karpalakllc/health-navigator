<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency department status (docs/urgent-care.md § Data), owner decision
 * 2026-10-07:
 *
 * - confirmed: the facility runs an emergency department (staff, or strong
 *   import evidence). Mirrors has_emergency_services = true.
 * - unconfirmed_likely: a public general or clinical hospital without a named
 *   emergency unit in the sources; shown as „итно одделение (непотврдено)“
 *   until staff decide.
 * - none: staff checked: no emergency department. The deriver never
 *   changes it again.
 * - null: nothing known.
 *
 * Backfill: every facility with has_emergency_services is `confirmed`
 * (idempotent: only rows still null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('emergency_department_status', 24)->nullable()->index();
        });

        DB::table('facilities')
            ->where('has_emergency_services', true)
            ->whereNull('emergency_department_status')
            ->update(['emergency_department_status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex(['emergency_department_status']);
            $table->dropColumn('emergency_department_status');
        });
    }
};
