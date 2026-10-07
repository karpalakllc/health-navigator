<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Каде веднаш“ (docs/urgent-care.md): what urgent care a facility offers.
 *
 * has_emergency_services (existing) is the emergency department — „ургентен
 * центар / ургентно одделение“. Next to it:
 *
 * - has_emergency_medical_service: a служба за итна медицинска помош (health
 *   centres; the 194 teams).
 * - has_on_duty_clinic: a дежурна амбуланта / on-duty practice outside the
 *   regular hours.
 * - has_dental_emergency: итна / дежурна стоматолошка служба.
 * - is_open_24h: the urgent service is open around the clock, as the
 *   institution itself states it (staff, or its own website's hours text).
 * - emergency_hours: the urgent service's hours, in the office_hours format;
 *   null = not confirmed (the site then says so and never shows „open now“).
 * - emergency_phone / urgent_care_note: a direct line and one short public
 *   note („влез од ул. …“).
 * - urgent_care_evidence: internal (never in the API) — what the imports
 *   read that suggests urgent care, and which flags the deriver set, so a
 *   flag staff switched off is never switched on again.
 * - urgent_care_checked_at: when staff last confirmed these fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->boolean('has_emergency_medical_service')->default(false);
            $table->boolean('has_on_duty_clinic')->default(false);
            $table->boolean('has_dental_emergency')->default(false);
            $table->boolean('is_open_24h')->default(false);
            $table->json('emergency_hours')->nullable();
            $table->string('emergency_phone', 50)->nullable();
            $table->string('urgent_care_note', 255)->nullable();
            $table->json('urgent_care_evidence')->nullable();
            $table->timestamp('urgent_care_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn([
                'has_emergency_medical_service',
                'has_on_duty_clinic',
                'has_dental_emergency',
                'is_open_24h',
                'emergency_hours',
                'emergency_phone',
                'urgent_care_note',
                'urgent_care_evidence',
                'urgent_care_checked_at',
            ]);
        });
    }
};
