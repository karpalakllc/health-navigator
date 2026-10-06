<?php

use App\Support\Licences\LicenceSpecialtySeedList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the Лекарска комора's specialty wording („Тип на специјализација“)
 * relates to the ФЗОМ wording and to our specialties, for matching licences
 * to imported doctors.
 *
 * Each row is one wording of one source, filed under a group (a stable key
 * such as „kardiologija“). A licence fits a doctor when the licence's group,
 * or one of its compatible groups, is among the doctor's groups. A row with
 * no group is unmapped (needs review); an ignored row is not a physician's
 * specialty (pharmacist, dentist, psychologist…). Seeded with the reviewed
 * mapping; staff edit it in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_specialty_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16);
            $table->string('source_text');
            $table->string('source_key');
            $table->string('group_key', 64)->nullable()->index();
            $table->json('compatible_groups')->nullable();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_ignored')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
        });

        LicenceSpecialtySeedList::insertMissing();
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_specialty_mappings');
    }
};
