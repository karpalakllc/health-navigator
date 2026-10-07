<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Дали ви помогна?“ and step drop-off (docs/urgent-care.md § Feedback).
 * Anonymous daily counters only: no visitor, session, address, text or exact
 * time, so a row can never be traced back to a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Yes / no votes per item (a guide, the urgent-care page, a guidance
        // outcome), and the optional reason chips chosen after a vote.
        Schema::create('feedback_counters', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('item_key', 96);
            $table->unsignedInteger('helpful')->default(0);
            $table->unsignedInteger('not_helpful')->default(0);

            $table->unique(['day', 'item_key'], 'feedback_counters_unique');
            $table->index('item_key');
            $table->index('day');
        });

        Schema::create('feedback_reason_counters', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('item_key', 96);
            $table->boolean('helpful');
            $table->string('reason', 32);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['day', 'item_key', 'helpful', 'reason'], 'feedback_reason_counters_unique');
            $table->index('day');
        });

        // How far visitors get in a multi-step flow: one count per step
        // reached (depth = its position on the path, for ordering).
        Schema::create('funnel_step_counters', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('funnel', 96);
            $table->string('step', 64);
            $table->unsignedSmallInteger('depth');
            $table->unsignedInteger('reached')->default(0);

            $table->unique(['day', 'funnel', 'step', 'depth'], 'funnel_step_counters_unique');
            $table->index(['funnel', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_step_counters');
        Schema::dropIfExists('feedback_reason_counters');
        Schema::dropIfExists('feedback_counters');
    }
};
