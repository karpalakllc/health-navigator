<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public removal trace: a review or forum post that was published and later
 * taken down keeps a placeholder with the date and a public category
 * (App\Enums\RemovalCategory). Content rejected before it was ever published
 * leaves no trace, so both columns stay null for it.
 *
 * Backfill: until now the only way to take published content down was hiding
 * it from the report queue (only published content can be reported), so a
 * rejected item with a „hidden“ report is known to have been published. Its
 * removal date is when that report was resolved and its category the most
 * common report reason. Anything else rejected is left alone: there is no
 * record that it was ever public.
 */
return new class extends Migration
{
    /** @var array<string, string> table => morph class, frozen at the time of writing */
    private const TABLES = [
        'reviews' => 'App\\Models\\Review',
        'forum_posts' => 'App\\Models\\ForumPost',
        'forum_topics' => 'App\\Models\\ForumTopic',
    ];

    /** Report reasons, which are also removal categories (frozen; same codes today). */
    private const REASON_ORDER = ['spam', 'abuse', 'false_information', 'personal_data', 'other'];

    public function up(): void
    {
        // One block per table (not a loop) so static analysis sees the columns.
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_category', 32)->nullable();
            $table->index('removed_at');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_category', 32)->nullable();
            $table->index('removed_at');
        });

        Schema::table('forum_topics', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_category', 32)->nullable();
            $table->index('removed_at');
        });

        foreach (self::TABLES as $table => $type) {
            $reports = DB::table('content_reports')
                ->where('reportable_type', $type)
                ->where('status', 'hidden')
                ->orderBy('id')
                ->get(['reportable_id', 'reason', 'resolved_at'])
                ->groupBy('reportable_id');

            foreach ($reports as $id => $rows) {
                $counts = $rows->countBy('reason')->all();
                $category = 'other';
                $best = 0;

                // Most reports win; a tie goes to the earlier reason in the list.
                foreach (self::REASON_ORDER as $reason) {
                    if (($counts[$reason] ?? 0) > $best) {
                        $best = $counts[$reason];
                        $category = $reason;
                    }
                }

                DB::table($table)
                    ->where('id', $id)
                    ->where('status', 'rejected')
                    ->whereNull('removed_at')
                    ->update([
                        'removed_at' => $rows->max('resolved_at') ?? now(),
                        'removal_category' => $category,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_removed_at_index');
            $table->dropColumn(['removed_at', 'removal_category']);
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropIndex('forum_posts_removed_at_index');
            $table->dropColumn(['removed_at', 'removal_category']);
        });

        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropIndex('forum_topics_removed_at_index');
            $table->dropColumn(['removed_at', 'removal_category']);
        });
    }
};
