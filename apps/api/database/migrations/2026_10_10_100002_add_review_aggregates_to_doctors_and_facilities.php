<?php

use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised approved-review aggregates (App\Support\ReviewAggregates keeps
 * them current). Every directory row used to carry two correlated COUNT/AVG
 * subqueries, and sort=rating / min_reviews evaluated them for every
 * published doctor before LIMIT could apply.
 */
return new class extends Migration
{
    /** @var array<string, string> table => morph class, frozen at the time of writing */
    private const TABLES = [
        'doctors' => 'App\\Models\\Doctor',
        'facilities' => 'App\\Models\\Facility',
    ];

    public function up(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('reviews_count')->default(0);
                // Truncated (not rounded) to 2 decimals; 0 when unrated.
                $blueprint->decimal('rating_avg', 3, 2)->default(0);
            });
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->index(['rating_avg', 'reviews_count'], 'doctors_rating_avg_reviews_count_index');
        });

        // Same formula as ReviewAggregates::backfillSql(), inlined so the
        // migration does not change if that class later does.
        foreach (self::TABLES as $table => $type) {
            $approved = "reviews.reviewable_id = {$table}.id and reviews.reviewable_type = ? and reviews.status = ?";
            $bindings = [$type, ReviewStatus::Approved->value, $type, ReviewStatus::Approved->value];

            DB::update(
                "update {$table} set "
                ."reviews_count = (select count(*) from reviews where {$approved}), "
                ."rating_avg = coalesce((select (sum(rating) * 100 / count(*)) / 100.0 from reviews where {$approved}), 0)",
                $bindings,
            );
        }
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex('doctors_rating_avg_reviews_count_index');
        });

        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['reviews_count', 'rating_avg']);
            });
        }
    }
};
