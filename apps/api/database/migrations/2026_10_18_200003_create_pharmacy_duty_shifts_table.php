<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-duty pharmacies (docs/urgent-care.md § On-duty pharmacies): one row per
 * pharmacy and day, from ФЗОМ's monthly „Распоред на дежурни аптеки“
 * (import:on-duty-pharmacies). A month is replaced as a whole on every
 * import. The pharmacy is linked when the name and town match one in the
 * directory; the published name, phone and hours are kept as ФЗОМ printed
 * them (no personal names: only the numbers of the phone column are kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_duty_shifts', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->index();
            $table->date('duty_date');
            $table->string('town', 64);
            $table->string('town_key', 64);
            $table->string('municipality', 64)->nullable();
            $table->string('pharmacy_name', 255);
            $table->string('name_key', 255);
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('phone', 255)->nullable();
            // all_day | hours | on_call | unknown
            $table->string('mode', 16);
            $table->string('hours_text', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->foreignId('import_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['duty_date', 'town_key']);
            $table->index(['facility_id', 'duty_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_duty_shifts');
    }
};
