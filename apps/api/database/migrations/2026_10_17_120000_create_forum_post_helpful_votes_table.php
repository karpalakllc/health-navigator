<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W8-C: „Корисно“ on forum replies, the same as on reviews — one vote per
 * member per reply. forum_posts.helpful_count is the denormalised total, kept
 * in step with the vote rows (App\Support\ForumPostHelpfulVotes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_post_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['forum_post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('helpful_count');
        });

        Schema::dropIfExists('forum_post_helpful_votes');
    }
};
