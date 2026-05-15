<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_flows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('intro_body')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index('is_published');
        });

        Schema::create('triage_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->string('type'); // single_select | multi_select
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['triage_flow_id', 'step_key']);
            $table->index(['triage_flow_id', 'sort_order']);
        });

        Schema::create('triage_step_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_step_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['triage_step_id', 'value']);
        });

        Schema::create('triage_red_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['triage_flow_id', 'code']);
        });

        Schema::create('triage_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('body');
            $table->json('handoffs')->nullable();
            $table->timestamps();

            $table->unique(['triage_flow_id', 'code']);
        });

        Schema::create('triage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->string('outcome_code');
            $table->json('conditions');
            $table->timestamps();

            $table->index(['triage_flow_id', 'priority']);
        });

        Schema::create('triage_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('triage_flow_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('terms_accepted_at');
            $table->boolean('emergency_stopped')->default(false);
            $table->string('outcome_code')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['triage_flow_id', 'created_at']);
        });

        Schema::create('triage_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('triage_session_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->json('values');
            $table->timestamps();

            $table->unique(['triage_session_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_session_answers');
        Schema::dropIfExists('triage_sessions');
        Schema::dropIfExists('triage_rules');
        Schema::dropIfExists('triage_outcomes');
        Schema::dropIfExists('triage_red_flags');
        Schema::dropIfExists('triage_step_options');
        Schema::dropIfExists('triage_steps');
        Schema::dropIfExists('triage_flows');
    }
};
