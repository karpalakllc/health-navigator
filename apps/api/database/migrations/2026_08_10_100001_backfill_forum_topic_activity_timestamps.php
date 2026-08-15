<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Content approved at creation never had published_at stamped, and topics without
 * replies never had last_post_at set. Both columns are ordered on, and PostgreSQL
 * sorts NULLs *first* under "ORDER BY ... DESC" — so those rows pinned themselves
 * to the top of every forum listing in production.
 *
 * The code fix only covers rows written from now on; this repairs the existing ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_topics')) {
            DB::table('forum_topics')
                ->where('status', 'approved')
                ->whereNull('published_at')
                ->update(['published_at' => DB::raw('created_at')]);

            DB::table('forum_topics')
                ->where('status', 'approved')
                ->whereNull('last_post_at')
                ->update(['last_post_at' => DB::raw('coalesce(published_at, created_at)')]);
        }

        if (Schema::hasTable('forum_posts')) {
            DB::table('forum_posts')
                ->where('status', 'approved')
                ->whereNull('published_at')
                ->update(['published_at' => DB::raw('created_at')]);
        }
    }

    /**
     * Not reversible — the original NULLs carried no information worth restoring.
     */
    public function down(): void
    {
        //
    }
};
