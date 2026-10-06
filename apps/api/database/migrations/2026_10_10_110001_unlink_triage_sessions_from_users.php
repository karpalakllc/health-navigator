<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guidance sessions stop being linked to accounts (owner decision 2026-10-06).
 *
 * The existing links are cleared before the column goes, so nothing survives
 * even on a rollback. Ownership is proven by a per-session secret instead; only
 * its SHA-256 is stored. Sessions started before this have no secret and can no
 * longer be continued — they are short-lived and purged on a schedule anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('triage_sessions')->update(['user_id' => null]);

        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->after('triage_flow_id');
        });
    }

    public function down(): void
    {
        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });

        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('triage_flow_id')->constrained()->nullOnDelete();
        });
    }
};
