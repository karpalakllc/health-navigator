<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Symptom guidance v2 (docs/triage-flows.md): flows loaded from files as
 * versioned definitions, clinician sign-off before publication, sessions that
 * run up to three flows, and anonymous weekly outcome counts.
 *
 * The v1 tables (steps, options, red flags, rules, outcomes) stay for the v1
 * flow and API; a v2 flow is a triage_flows row with a `key`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triage_flows', function (Blueprint $table) {
            $table->string('key', 64)->nullable()->unique()->after('id');
        });

        Schema::create('triage_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // draft | reviewed | published | retired
            $table->string('status', 16)->default('draft');
            $table->json('definition');
            $table->char('definition_hash', 64);
            $table->string('source_path')->nullable();
            $table->json('lint_report')->nullable();
            $table->unsignedInteger('lint_errors')->default(0);
            $table->unsignedInteger('lint_warnings')->default(0);
            // Set only for flows that were live before sign-off existed.
            $table->string('review_exempt_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['triage_flow_id', 'version']);
            $table->index('status');
        });

        Schema::create('triage_flow_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_version_id')->constrained()->cascadeOnDelete();
            // approved | changes_requested
            $table->string('decision', 24);
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_registration', 64)->nullable();
            $table->date('reviewed_on');
            $table->text('note');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('triage_sessions', function (Blueprint $table) {
            // v2 sessions run several flows, listed in triage_session_flows.
            $table->foreignId('triage_flow_id')->nullable()->change();
            $table->unsignedTinyInteger('engine')->default(1)->after('id');
            $table->string('outcome_level', 32)->nullable()->after('outcome_code');
        });

        Schema::create('triage_session_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('triage_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triage_flow_version_id')->constrained()->restrictOnDelete();
            $table->string('flow_key', 64);
            $table->unsignedTinyInteger('position');
            $table->string('outcome_id', 64)->nullable();
            $table->string('outcome_level', 32)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['triage_session_id', 'position']);
        });

        /*
         * Aggregates only: how often each flow ended in each outcome, per ISO
         * week. Nothing here points back to a session or an answer, so the
         * counts outlive the 90-day session purge.
         */
        Schema::create('triage_outcome_stats', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->string('flow_key', 64);
            $table->string('outcome_id', 80);
            $table->string('outcome_level', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['week_start', 'flow_key', 'outcome_id']);
            $table->index(['flow_key', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_outcome_stats');
        Schema::dropIfExists('triage_session_flows');

        // v2 sessions have no single flow; they cannot survive the rollback.
        DB::table('triage_sessions')->where('engine', 2)->delete();

        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->dropColumn(['engine', 'outcome_level']);
        });

        Schema::table('triage_sessions', function (Blueprint $table) {
            $table->foreignId('triage_flow_id')->nullable(false)->change();
        });

        Schema::dropIfExists('triage_flow_reviews');
        Schema::dropIfExists('triage_flow_versions');

        // v2 flows (with a key) have no v1 steps; drop them with the column.
        DB::table('triage_flows')->whereNotNull('key')->delete();

        Schema::table('triage_flows', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
