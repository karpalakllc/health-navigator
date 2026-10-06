<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging for the Лекарска комора „Листа на доктори со важечки лиценци“.
 *
 * - komora_licences: one row per licence number, as last published (name,
 *   specialty, expiry), when it was first and last on the list, since when it
 *   is missing from it, and what matching did with it. Internal only: the
 *   licence number is a matching key, never shown publicly. Rows are kept
 *   when a licence drops off the list — that is a review signal, and nothing
 *   is unpublished automatically.
 * - komora_licence_downloads: each fetch of a list file (URL, ETag,
 *   Last-Modified, hash, private storage path) for conditional requests and
 *   the audit trail. The raw file is kept on the private disk for the last
 *   few runs only (config licences.komora.keep_snapshots).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komora_licences', function (Blueprint $table) {
            $table->id();
            $table->string('licence_number', 16)->unique();
            $table->string('full_name');
            $table->string('name_key')->index();
            $table->string('specialty')->nullable();
            $table->string('specialty_key')->nullable()->index();
            $table->date('valid_until')->nullable();
            $table->date('list_date');
            $table->string('source_reference', 128)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->date('missing_since')->nullable()->index();
            $table->string('outcome', 32)->nullable()->index();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->json('candidate_doctor_ids')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index('valid_until');
        });

        Schema::create('komora_licence_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('batch', 32)->index();
            $table->string('url', 2048);
            $table->string('label', 64);
            $table->string('status', 16);
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('storage_path')->nullable();
            $table->date('list_date')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komora_licence_downloads');
        Schema::dropIfExists('komora_licences');
    }
};
