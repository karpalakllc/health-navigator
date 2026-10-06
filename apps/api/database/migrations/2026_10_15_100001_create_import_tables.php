<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The import core.
 *
 * - import_runs: one row per run of an importer (dry run or apply), with the
 *   source files' metadata, counts and the path of the private diff summary.
 * - source_records: the last normalised payload we accepted per source and
 *   external key (a facility, a doctor), with its hash so an unchanged record
 *   is skipped on the next run. Only fields we are allowed to keep are in the
 *   payload: the parser drops the excluded ones before anything is stored.
 * - field_provenance: per (record, field) which source wrote the value, the
 *   value it wrote, when, and whether staff locked the field. A field whose
 *   current value differs from what the import last wrote was edited by
 *   someone else and is never overwritten silently.
 * - import_review_items: the staff queue (new, changed, conflict, missing,
 *   unmatched), idempotent per (source, kind, item_key) while open.
 * - specialty_aliases: a source's specialty wording → our specialty, or
 *   excluded (non-physician professions), or unmapped (specialty_id null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->boolean('dry_run')->default(false);
            $table->string('status', 16);
            $table->foreignId('triggered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('source_meta')->nullable();
            $table->json('counts')->nullable();
            $table->string('diff_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['source', 'status', 'started_at']);
        });

        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('external_key', 191);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->string('hash', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->foreignId('last_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source', 'external_key']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('field_provenance', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('field', 48);
            $table->string('source', 32)->nullable();
            $table->foreignId('source_record_id')->nullable()->constrained('source_records')->nullOnDelete();
            $table->text('value')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->boolean('locked')->default(false);
            $table->foreignId('locked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'field']);
        });

        Schema::create('import_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->string('source', 32);
            $table->string('kind', 16);
            $table->string('item_key', 128);
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title');
            $table->json('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->timestamps();

            $table->index(['status', 'kind', 'created_at']);
            $table->index(['source', 'kind', 'item_key', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('specialty_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('raw');
            $table->string('raw_key');
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->timestamps();

            $table->unique(['source', 'raw_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialty_aliases');
        Schema::dropIfExists('import_review_items');
        Schema::dropIfExists('field_provenance');
        Schema::dropIfExists('source_records');
        Schema::dropIfExists('import_runs');
    }
};
