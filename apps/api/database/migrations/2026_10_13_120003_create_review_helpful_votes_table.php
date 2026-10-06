<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Корисно“: one vote per member per review. reviews.helpful_count is the
 * denormalised total, kept in step atomically with the vote rows
 * (App\Support\ReviewHelpfulVotes) so lists never count rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['review_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('helpful_count');
        });

        Schema::dropIfExists('review_helpful_votes');
    }
};
