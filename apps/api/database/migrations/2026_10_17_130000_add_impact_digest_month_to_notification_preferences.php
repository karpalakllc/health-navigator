<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last month (YYYY-MM) the monthly impact digest was handled for a
 * member: the digest goes out at most once per member per month, and a run
 * missed on the 1st is caught up once by the next daily run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->string('impact_digest_month', 7)->nullable()->after('digest_invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('impact_digest_month');
        });
    }
};
