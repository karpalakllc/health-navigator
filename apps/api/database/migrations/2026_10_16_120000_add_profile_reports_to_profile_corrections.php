<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * „Пријави профил“ (W7-C) shares the corrections queue as type `report`:
 *
 * - `report_reason`: ProfileReportReason;
 * - `message` becomes optional, since a report's note is;
 * - `reporter_hash`: for a guest, a keyed hash of their address and the
 *   profile (never the address itself, and different for every profile),
 *   so independent reporters can be counted. Erased when the report is
 *   closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_corrections', function (Blueprint $table) {
            $table->string('report_reason', 32)->nullable()->after('field');
            $table->string('reporter_hash', 64)->nullable()->after('user_id');
            $table->text('message')->nullable()->change();

            $table->index(['subject_type', 'subject_id', 'type', 'status'], 'profile_corrections_subject_open_index');
        });
    }

    /**
     * Lossy: profile reports cannot exist without these columns, so they are
     * deleted before `message` is required again.
     */
    public function down(): void
    {
        DB::table('profile_corrections')->where('type', 'report')->delete();
        DB::table('profile_corrections')->whereNull('message')->update(['message' => '']);

        Schema::table('profile_corrections', function (Blueprint $table) {
            $table->text('message')->nullable(false)->change();
            $table->dropIndex('profile_corrections_subject_open_index');
            $table->dropColumn(['report_reason', 'reporter_hash']);
        });
    }
};
