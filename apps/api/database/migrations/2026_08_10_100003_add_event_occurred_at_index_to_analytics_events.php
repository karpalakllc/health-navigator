<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every analytics read filters on `event` AND `occurred_at`, but the table only
 * had separate single-column indexes. Neither can serve the composite predicate
 * efficiently once the table grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['event', 'occurred_at'], 'analytics_events_event_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_events_event_occurred_at_index');
        });
    }
};
