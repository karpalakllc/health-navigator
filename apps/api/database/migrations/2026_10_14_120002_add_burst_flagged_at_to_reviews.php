<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-only signal: set on every review of a profile that received five or
 * more reviews within 24 hours (App\Support\ReviewBurstDetector). Nothing
 * acts on it automatically; it is a badge and a filter in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('burst_flagged_at')->nullable();
            $table->index('burst_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_burst_flagged_at_index');
            $table->dropColumn('burst_flagged_at');
        });
    }
};
