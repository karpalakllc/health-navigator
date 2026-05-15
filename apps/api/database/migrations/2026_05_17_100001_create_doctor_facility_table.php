<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_facility', function (Blueprint $table) {
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['doctor_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_facility');
    }
};
