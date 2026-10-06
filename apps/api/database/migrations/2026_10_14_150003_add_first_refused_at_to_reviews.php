<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a moderator first refused a review that its author then edited and
 * resent. Resending clears the decision on the row (it is pending again), so
 * without this the /transparency figures lost the first refusal. Reviews
 * resent before this column existed have none on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('first_refused_at')->nullable()->after('resubmitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('first_refused_at');
        });
    }
};
