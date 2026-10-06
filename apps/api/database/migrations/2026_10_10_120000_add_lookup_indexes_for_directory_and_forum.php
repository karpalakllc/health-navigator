<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes found missing by EXPLAIN against PerfSeeder volume (docs/performance.md).
 *
 * Neither PostgreSQL nor SQLite indexes a foreign key column on its own, so a
 * composite primary/unique key only serves lookups by its LEADING column:
 *
 * - pharmacy_product (facility_id, product_id): the product catalogue's
 *   from_price / offer_count subqueries filter by product_id and were a
 *   sequential scan of the whole table per product row.
 * - doctor_facility (doctor_id, facility_id): a facility's doctors.
 * - department_facility unique (facility_id, department_id): the department
 *   filter on GET /facilities, and cascades when a department is deleted.
 * - analytics_events.user_id / triage_sessions.user_id: ON DELETE SET NULL
 *   scanned the whole table for every deleted user.
 *
 * Replaced, not just added:
 *
 * - reviews (reviewable_type, reviewable_id) from morphs() is a strict prefix
 *   of (reviewable_type, reviewable_id, status), so it only cost writes. The
 *   composite gains published_at, the order of every public review listing.
 * - forum_posts (forum_topic_id, status, created_at): topic pages order
 *   approved posts by published_at, never created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_product', function (Blueprint $table) {
            $table->index(['product_id', 'facility_id'], 'pharmacy_product_product_id_facility_id_index');
        });

        Schema::table('doctor_facility', function (Blueprint $table) {
            $table->index('facility_id', 'doctor_facility_facility_id_index');
        });

        Schema::table('department_facility', function (Blueprint $table) {
            $table->index('department_id', 'department_facility_department_id_index');
        });

        if (Schema::hasColumn('analytics_events', 'user_id')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                $table->index('user_id', 'analytics_events_user_id_index');
            });
        }

        // PostgreSQL only: the column may be dropped by a later migration, and
        // SQLite's native DROP COLUMN refuses an indexed column, whereas
        // PostgreSQL drops the dependent index with it.
        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('triage_sessions', 'user_id')) {
            Schema::table('triage_sessions', function (Blueprint $table) {
                $table->index('user_id', 'triage_sessions_user_id_index');
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_reviewable_type_reviewable_id_index');
            $table->dropIndex('reviews_reviewable_type_reviewable_id_status_index');
            $table->index(
                ['reviewable_type', 'reviewable_id', 'status', 'published_at'],
                'reviews_reviewable_status_published_at_index',
            );
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropIndex('forum_posts_forum_topic_id_status_created_at_index');
            $table->index(
                ['forum_topic_id', 'status', 'published_at'],
                'forum_posts_forum_topic_id_status_published_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropIndex('forum_posts_forum_topic_id_status_published_at_index');
            $table->index(['forum_topic_id', 'status', 'created_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_reviewable_status_published_at_index');
            $table->index(['reviewable_type', 'reviewable_id', 'status']);
            $table->index(['reviewable_type', 'reviewable_id']);
        });

        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('triage_sessions', 'user_id')) {
            DB::statement('DROP INDEX IF EXISTS triage_sessions_user_id_index');
        }

        if (Schema::hasColumn('analytics_events', 'user_id')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                $table->dropIndex('analytics_events_user_id_index');
            });
        }

        Schema::table('department_facility', function (Blueprint $table) {
            $table->dropIndex('department_facility_department_id_index');
        });

        Schema::table('doctor_facility', function (Blueprint $table) {
            $table->dropIndex('doctor_facility_facility_id_index');
        });

        Schema::table('pharmacy_product', function (Blueprint $table) {
            $table->dropIndex('pharmacy_product_product_id_facility_id_index');
        });
    }
};
