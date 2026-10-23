<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-only: whether a review was started from the shared „write a review“
 * link (sent by the web only with statistics consent), and the moderation
 * signal for an unusual burst of such reviews on one profile
 * (App\Support\ReviewBurstDetector::checkSharedLink). Neither is public and
 * nothing acts on them automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('via_shared_link')->default(false);
            $table->timestamp('shared_link_burst_flagged_at')->nullable();
            $table->index('shared_link_burst_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_shared_link_burst_flagged_at_index');
            $table->dropColumn(['via_shared_link', 'shared_link_burst_flagged_at']);
        });
    }
};
