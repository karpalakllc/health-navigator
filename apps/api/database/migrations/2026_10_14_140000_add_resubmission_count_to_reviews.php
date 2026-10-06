<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member whose review was refused before publication may edit it and send
 * it to moderation once more (owner decision, 2026-10-14). The count makes a
 * second refusal final; resubmitted_at tells moderators it is a second try.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('resubmission_count')->default(0);
            $table->timestamp('resubmitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['resubmission_count', 'resubmitted_at']);
        });
    }
};
