<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-aspect sub-ratings (App\Enums\ReviewAspect), one row per
 * review and aspect. doctors/facilities.aspect_ratings is the denormalised
 * per-aspect count and average over approved reviews, kept current by
 * App\Support\ReviewAggregates like reviews_count / rating_avg. No backfill:
 * no review has aspects yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_aspect_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('aspect', 32);
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['review_id', 'aspect']);
        });

        foreach (['doctors', 'facilities'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->json('aspect_ratings')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'facilities'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('aspect_ratings');
            });
        }

        Schema::dropIfExists('review_aspect_ratings');
    }
};
