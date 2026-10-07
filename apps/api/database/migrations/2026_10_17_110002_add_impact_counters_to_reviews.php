<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W8-B impact counters, all without personal data.
 *
 * reviews.view_count: how many times the review card was on a visitor's
 * screen on the profile (App\Support\ReviewViews: reported by the browser,
 * at most once a day per network, crawlers and the author excluded).
 * review_views_monthly: the same per calendar month, for the monthly digest.
 * reviews.helpful_notified_count: the „Корисно“ total the author was last
 * told about (batched notification). reviews.reply_notified_at: the author was
 * told about the profile's public reply (once per reply).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_notified_count')->default(0);
            $table->timestamp('reply_notified_at')->nullable();
        });

        // Replies already public before this release are not news: nobody
        // is mailed about them when their review is next saved.
        DB::table('reviews')
            ->where('response_status', 'approved')
            ->whereNotNull('response_body')
            ->update(['reply_notified_at' => DB::raw('coalesce(response_at, updated_at)')]);

        // Votes given before this release are not announced either.
        DB::table('reviews')->update(['helpful_notified_count' => DB::raw('helpful_count')]);

        Schema::create('review_views_monthly', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('views')->default(0);

            $table->primary(['review_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_views_monthly');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['view_count', 'helpful_notified_count', 'reply_notified_at']);
        });
    }
};
