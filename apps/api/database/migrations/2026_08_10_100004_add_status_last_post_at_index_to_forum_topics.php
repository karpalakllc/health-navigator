<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recent-topics feed and unified search filter on status and order by
 * last_post_at with no category filter, so the existing
 * (forum_category_id, status, is_pinned, last_post_at) index cannot serve them —
 * its leading column is absent from the predicate.
 *
 * Note: an index on reviews(published_at) was considered and rejected. The
 * existing (reviewable_type, reviewable_id, status) index already covers that
 * WHERE clause, and a standalone published_at index has an unselective leading
 * column for those queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->index(['status', 'last_post_at'], 'forum_topics_status_last_post_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropIndex('forum_topics_status_last_post_at_index');
        });
    }
};
