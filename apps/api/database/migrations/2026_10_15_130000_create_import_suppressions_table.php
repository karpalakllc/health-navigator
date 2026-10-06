<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * import_suppressions: doctors no import may create or publish again — the
 * profile was removed after an upheld objection, or staff deleted it.
 *
 * Keyed by what the sources carry: the ФЗО facsimile, the licence number,
 * the source records that fed the profile (`source:external_key`), and the
 * normalised name + town for profiles that had no stronger key (staff-made,
 * or a website match). `doctor_id` has no foreign key on purpose: the row
 * must outlive a hard delete of the profile. Staff lift a suppression under
 * Data import → Suppressed profiles; a restored (undeleted) profile lifts
 * its own "deleted" suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_suppressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id')->nullable()->index();
            $table->string('fzo_facsimile', 16)->nullable()->index();
            $table->string('licence_number', 16)->nullable()->index();
            $table->string('name_key_sorted')->nullable()->index();
            $table->string('city_key')->nullable();
            $table->json('source_keys')->nullable();
            $table->string('label');
            $table->string('reason', 16);
            $table->foreignId('profile_correction_id')->nullable()->constrained('profile_corrections')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_suppressions');
    }
};
