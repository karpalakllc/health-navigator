<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W8-C: a member's contributor levels („Активен рецензент“, „Помошник“…),
 * one row per member who has published anything. Derived data only: every
 * column is recomputed from public content by App\Support\Levels\ContributorLevels
 * (on moderation and vote events, and nightly), so the row can be dropped and
 * rebuilt at any time. See docs/levels.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_levels', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('review_points')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('review_helpful_count')->default(0);
            $table->unsignedTinyInteger('review_level')->default(0);

            $table->unsignedInteger('forum_points')->default(0);
            $table->unsignedInteger('forum_topics_count')->default(0);
            $table->unsignedInteger('forum_replies_count')->default(0);
            $table->unsignedInteger('forum_helpful_count')->default(0);
            $table->unsignedTinyInteger('forum_level')->default(0);

            $table->timestamp('computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_levels');
    }
};
