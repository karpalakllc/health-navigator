<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When staff were emailed about a report (reports:alert-staff). Set in
 * batches, so each new report is announced once and a burst of reports
 * becomes one email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            $table->timestamp('staff_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            $table->dropColumn('staff_alerted_at');
        });
    }
};
