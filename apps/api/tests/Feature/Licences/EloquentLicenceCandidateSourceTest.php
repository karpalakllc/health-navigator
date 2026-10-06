<?php

namespace Tests\Feature\Licences;

use App\Models\Doctor;
use App\Models\Specialty;
use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidate;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\EloquentLicenceCandidateSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Candidates from the doctors table, on the columns the import core (W6-A)
 * adds to it.
 */
class EloquentLicenceCandidateSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // INTEGRATION SHIM: the import core's migration
        // (2026_10_15_100000_add_import_keys_to_directory_tables, branch
        // feat/w6-import-core) adds these columns. Until both branches are
        // merged this test adds the ones it needs; afterwards the check
        // finds them and does nothing.
        if (! Schema::hasColumn('doctors', 'name_key')) {
            Schema::table('doctors', function (Blueprint $table): void {
                $table->string('licence_number', 16)->nullable()->unique();
                $table->string('name_key')->nullable()->index();
                $table->string('name_key_sorted')->nullable()->index();
                $table->string('import_source', 32)->nullable();
            });
        }
    }

    private function doctor(string $name, string $importSource = 'fzom', array $specialties = [], ?string $licence = null): Doctor
    {
        $doctor = Doctor::factory()->create(['full_name' => $name]);

        DB::table('doctors')->where('id', $doctor->id)->update([
            'name_key' => NameKey::for($name),
            'name_key_sorted' => NameKey::sorted($name),
            'import_source' => $importSource,
            'licence_number' => $licence,
        ]);

        foreach ($specialties as $slug => $specialtyName) {
            $specialty = Specialty::query()->firstOrCreate(['slug' => $slug], ['name' => $specialtyName]);
            $doctor->specialties()->attach($specialty->id, ['is_primary' => true]);
        }

        return $doctor;
    }

    /**
     * @return list<int>
     */
    private function ids(string $name): array
    {
        return array_map(fn (LicenceCandidate $candidate): int => $candidate->doctorId, app(LicenceCandidateSource::class)->candidatesFor($name));
    }

    public function test_it_is_the_bound_source_and_matches_names_in_either_order_and_spelling(): void
    {
        $this->assertInstanceOf(EloquentLicenceCandidateSource::class, app(LicenceCandidateSource::class));

        $first = $this->doctor('Ана Примеровска-Огледовска', specialties: ['interna-medicina-test' => 'Интерна медицина']);
        $swapped = $this->doctor('Примеровска Огледовска Ана');
        $this->doctor('Ана Примеровска');

        $this->assertSame([$first->id, $swapped->id], $this->ids('АНА ПРИМЕРОВСКА ОГЛЕДОВСКА'));

        $candidate = app(LicenceCandidateSource::class)->candidatesFor('АНА ПРИМЕРОВСКА-ОГЛЕДОВСКА')[0];
        $this->assertSame(['Интерна медицина'], $candidate->specialtyNames);
        $this->assertSame(['interna-medicina-test'], $candidate->specialtySlugs);
    }

    public function test_only_imported_non_dental_live_profiles_are_candidates(): void
    {
        $imported = $this->doctor('Лена Кандидатовска');
        $this->doctor('Лена Кандидатовска', importSource: 'manual');
        $this->doctor('Лена Кандидатовска', specialties: ['stomatologija-opsta' => 'Општа стоматологија']);
        $this->doctor('Лена Кандидатовска')->delete();

        $this->assertSame([$imported->id], $this->ids('ЛЕНА КАНДИДАТОВСКА'));

        config(['licences.match.imported_source' => null]);
        $this->assertCount(2, $this->ids('ЛЕНА КАНДИДАТОВСКА'));
    }

    public function test_it_finds_the_profile_holding_a_licence_number(): void
    {
        $holder = $this->doctor('Маја Носителска', licence: '0001234');

        $this->assertSame($holder->id, app(LicenceCandidateSource::class)->doctorIdForLicence('0001234'));
        $this->assertNull(app(LicenceCandidateSource::class)->doctorIdForLicence('0009999'));
        $this->assertSame('0001234', app(LicenceCandidateSource::class)->candidatesFor('МАЈА НОСИТЕЛСКА')[0]->licenceNumber);
    }
}
