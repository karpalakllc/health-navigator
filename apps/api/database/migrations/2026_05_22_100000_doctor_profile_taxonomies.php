<?php

use App\Models\ClinicalInterest;
use App\Models\Doctor;
use App\Models\Language;
use App\Models\Procedure;
use App\Support\Slug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('clinical_interests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('doctor_language', function (Blueprint $table) {
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'language_id']);
        });

        Schema::create('doctor_clinical_interest', function (Blueprint $table) {
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_interest_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'clinical_interest_id']);
        });

        Schema::create('doctor_procedure', function (Blueprint $table) {
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'procedure_id']);
        });

        $this->migrateJsonTaxonomies();

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn(['languages', 'clinical_interests', 'procedures']);
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->json('languages')->nullable();
            $table->json('clinical_interests')->nullable();
            $table->json('procedures')->nullable();
        });

        foreach (Doctor::withTrashed()->cursor() as $doctor) {
            $doctor->forceFill([
                'languages' => $doctor->languages()->orderBy('name')->pluck('name')->all(),
                'clinical_interests' => $doctor->clinicalInterests()->orderBy('name')->pluck('name')->all(),
                'procedures' => $doctor->procedures()->orderBy('name')->pluck('name')->all(),
            ])->saveQuietly();
        }

        Schema::dropIfExists('doctor_procedure');
        Schema::dropIfExists('doctor_clinical_interest');
        Schema::dropIfExists('doctor_language');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('clinical_interests');
        Schema::dropIfExists('languages');
    }

    private function migrateJsonTaxonomies(): void
    {
        if (! Schema::hasColumn('doctors', 'languages')) {
            return;
        }

        foreach (Doctor::withTrashed()->cursor() as $doctor) {
            $attributes = $doctor->getAttributes();

            $this->syncPivotFromJson(
                $doctor,
                Language::class,
                'languages',
                $attributes['languages'] ?? null,
            );

            $this->syncPivotFromJson(
                $doctor,
                ClinicalInterest::class,
                'clinicalInterests',
                $attributes['clinical_interests'] ?? null,
            );

            $this->syncPivotFromJson(
                $doctor,
                Procedure::class,
                'procedures',
                $attributes['procedures'] ?? null,
            );
        }
    }

    /**
     * @param  class-string<Language|ClinicalInterest|Procedure>  $modelClass
     */
    private function syncPivotFromJson(Doctor $doctor, string $modelClass, string $relation, mixed $json): void
    {
        $names = $this->decodeNameList($json);

        if ($names === []) {
            return;
        }

        $ids = [];

        foreach ($names as $name) {
            $slug = Slug::fromName($name);
            $record = $modelClass::withTrashed()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_published' => true, 'sort_order' => 0],
            );

            if ($record->trashed()) {
                $record->restore();
            }

            if ($record->name !== $name) {
                $record->update(['name' => $name]);
            }

            $ids[] = $record->id;
        }

        $doctor->{$relation}()->sync($ids);
    }

    /**
     * @return list<string>
     */
    private function decodeNameList(mixed $json): array
    {
        if ($json === null) {
            return [];
        }

        $decoded = is_string($json) ? json_decode($json, true) : $json;

        if (! is_array($decoded)) {
            return [];
        }

        $names = [];

        foreach ($decoded as $item) {
            if (! is_string($item)) {
                continue;
            }

            $trimmed = trim($item);

            if ($trimmed !== '') {
                $names[] = $trimmed;
            }
        }

        return array_values(array_unique($names));
    }
};
