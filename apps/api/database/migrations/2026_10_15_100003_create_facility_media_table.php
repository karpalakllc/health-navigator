<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Images taken from an institution's own website (logo, cover photos), each
 * with the URL it came from, so staff can answer "where is this from" and
 * take one down in one click. A removed image keeps its row (status
 * "removed", file deleted) so a re-import never brings it back.
 *
 * field_provenance.source_url: the page a value was read from, for sources
 * that are web pages rather than one register file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('source', 32);
            $table->text('source_url')->nullable();
            $table->string('content_hash', 64);
            $table->string('path')->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('removed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'kind', 'content_hash']);
        });

        Schema::table('field_provenance', function (Blueprint $table) {
            $table->text('source_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('field_provenance', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });

        Schema::dropIfExists('facility_media');
    }
};
