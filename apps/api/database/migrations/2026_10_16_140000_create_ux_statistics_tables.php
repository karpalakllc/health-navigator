<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous UX statistics (docs/ux-heatmaps.md). Only daily counters per page
 * template and device class: no visitor, session, address, text or exact time
 * is stored, so a row can never be traced back to a visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Click counts per position cell: x is a percentage of the page width,
        // y a 10 px band from the top of the document.
        Schema::create('ux_heatmap_cells', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('route', 64);
            $table->string('viewport_class', 8);
            $table->unsignedSmallInteger('width_bucket');
            $table->unsignedTinyInteger('x_bucket');
            $table->unsignedSmallInteger('y_bucket');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('dead_clicks')->default(0);
            $table->unsignedInteger('rage_clicks')->default(0);

            $table->unique(
                ['day', 'route', 'viewport_class', 'width_bucket', 'x_bucket', 'y_bucket'],
                'ux_heatmap_cells_unique',
            );
            $table->index(['route', 'viewport_class', 'day'], 'ux_heatmap_cells_lookup');
            $table->index('day');
        });

        // Click counts per structural target key (e.g. `doctor-card/heading`).
        Schema::create('ux_element_stats', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('route', 64);
            $table->string('viewport_class', 8);
            $table->string('target_key', 96);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('dead_clicks')->default(0);
            $table->unsignedInteger('rage_clicks')->default(0);

            $table->unique(['day', 'route', 'viewport_class', 'target_key'], 'ux_element_stats_unique');
            $table->index(['route', 'day'], 'ux_element_stats_lookup');
            $table->index('day');
        });

        // Page views with the deepest scroll milestone each reached, and how
        // long until the first click.
        Schema::create('ux_page_stats', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('route', 64);
            $table->string('viewport_class', 8);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('scroll_25')->default(0);
            $table->unsignedInteger('scroll_50')->default(0);
            $table->unsignedInteger('scroll_75')->default(0);
            $table->unsignedInteger('scroll_90')->default(0);
            $table->unsignedInteger('scroll_100')->default(0);
            $table->unsignedInteger('tfi_under_1s')->default(0);
            $table->unsignedInteger('tfi_1_3s')->default(0);
            $table->unsignedInteger('tfi_3_10s')->default(0);
            $table->unsignedInteger('tfi_10_30s')->default(0);
            $table->unsignedInteger('tfi_over_30s')->default(0);
            $table->unsignedInteger('tfi_none')->default(0);

            $table->unique(['day', 'route', 'viewport_class'], 'ux_page_stats_unique');
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ux_page_stats');
        Schema::dropIfExists('ux_element_stats');
        Schema::dropIfExists('ux_heatmap_cells');
    }
};
