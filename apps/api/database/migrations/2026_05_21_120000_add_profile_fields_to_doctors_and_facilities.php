<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('subspecialty')->nullable()->after('title');
            $table->unsignedSmallInteger('years_experience')->nullable()->after('bio');
            $table->text('education')->nullable()->after('years_experience');
            $table->json('languages')->nullable()->after('education');
            $table->json('clinical_interests')->nullable()->after('languages');
            $table->json('procedures')->nullable()->after('clinical_interests');
            $table->string('consultation_fee_note')->nullable()->after('procedures');
            $table->string('avatar_url')->nullable()->after('consultation_fee_note');
            $table->json('office_hours')->nullable()->after('avatar_url');
            $table->boolean('accepts_new_patients')->default(true)->after('office_hours');
            $table->boolean('is_featured')->default(false)->after('accepts_new_patients');

            $table->index('is_featured');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->string('website')->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('website');
            $table->json('office_hours')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn([
                'subspecialty',
                'years_experience',
                'education',
                'languages',
                'clinical_interests',
                'procedures',
                'consultation_fee_note',
                'avatar_url',
                'office_hours',
                'accepts_new_patients',
                'is_featured',
            ]);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['website', 'avatar_url', 'office_hours']);
        });
    }
};
