<?php

namespace Tests\Feature\UrgentCare;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SourceRecord;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\UrgentCare\UrgentCareDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Urgent-care flags from imported wording: what switches them on, and that
 * staff decisions win (docs/urgent-care.md § Data).
 */
class UrgentCareDeriverTest extends TestCase
{
    use RefreshDatabase;

    private function withWorkUnit(Facility $facility, string $workUnit): void
    {
        $doctor = Doctor::factory()->create();
        $doctor->facilities()->attach($facility->getKey(), ['work_unit' => $workUnit, 'source' => 'fzom']);
    }

    private function withWebsite(Facility $facility, array $payload): void
    {
        SourceRecord::query()->create([
            'source' => 'website',
            'external_key' => 'institution:test:'.$facility->getKey(),
            'subject_type' => 'facility',
            'subject_id' => $facility->getKey(),
            'payload' => $payload,
            'hash' => sha1((string) json_encode($payload)),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function test_strong_wording_switches_flags_on_and_candidates_stay_off(): void
    {
        $hospital = Facility::factory()->create(['name' => 'ЈЗУ Општа болница Тестово', 'type' => FacilityType::Hospital]);
        $centre = Facility::factory()->create(['name' => 'ЈЗУ Здравствен дом Тестово', 'type' => FacilityType::Clinic]);
        $psychiatry = Facility::factory()->create(['name' => 'ЈЗУ Психијатриска болница', 'type' => FacilityType::Hospital]);
        $this->withWorkUnit($hospital, 'Ургентен центар');
        $this->withWebsite($hospital, ['hours' => 'Амбуланти 08:00–15:00; Ургентен центар 24/7', 'departments' => ['Кардиологија']]);
        $this->withWorkUnit($centre, 'Служба за итна медицинска помош и домашно лекување');
        $this->withWebsite($centre, ['departments' => ['Итна стоматолошка помош'], 'hours' => null]);
        $this->withWorkUnit($psychiatry, 'Машки оддел за ургентна психијатрија');

        $summary = app(UrgentCareDeriver::class)->run();

        $hospital->refresh();
        $this->assertTrue($hospital->has_emergency_services);
        $this->assertTrue($hospital->is_open_24h);
        $this->assertFalse($hospital->has_emergency_medical_service);
        $this->assertNull($hospital->emergency_hours, 'Hours are never written.');

        $centre->refresh();
        $this->assertTrue($centre->has_emergency_medical_service);
        $this->assertTrue($centre->has_dental_emergency);
        $this->assertFalse($centre->has_emergency_services);
        $this->assertFalse($centre->is_open_24h);

        $psychiatry->refresh();
        $this->assertFalse($psychiatry->has_emergency_services);
        $this->assertTrue($psychiatry->urgent_care_evidence['has_candidates']);
        $this->assertSame('Машки оддел за ургентна психијатрија', $psychiatry->urgent_care_evidence['items'][0]['text']);

        $this->assertSame(4, $summary['flags_set']);
    }

    public function test_a_flag_staff_switched_off_stays_off_and_confirmed_facilities_are_left_alone(): void
    {
        $cleared = Facility::factory()->create(['name' => 'ЈЗУ Здравствен дом А', 'type' => FacilityType::Clinic]);
        $confirmed = Facility::factory()->create(['name' => 'ЈЗУ Здравствен дом Б', 'type' => FacilityType::Clinic, 'urgent_care_checked_at' => now()]);
        $this->withWorkUnit($cleared, 'Итна Помош');
        $this->withWorkUnit($confirmed, 'Итна Помош');

        app(UrgentCareDeriver::class)->run();
        $this->assertTrue($cleared->refresh()->has_emergency_medical_service);
        $this->assertFalse($confirmed->refresh()->has_emergency_medical_service);

        // Staff switch it off; the next run (e.g. the weekly import) keeps it off.
        $cleared->forceFill(['has_emergency_medical_service' => false])->save();
        $summary = app(UrgentCareDeriver::class)->run();

        $this->assertFalse($cleared->refresh()->has_emergency_medical_service);
        $this->assertSame(1, $summary['kept_off_by_staff']);
        $this->assertSame(1, $summary['confirmed_by_staff']);
        $this->assertSame(0, $summary['flags_set']);
    }

    public function test_pharmacies_are_ignored_and_the_dry_run_writes_nothing(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create(['name' => 'Аптека Ургентен центар']);
        $centre = Facility::factory()->create(['name' => 'ЈЗУ Здравствен дом В', 'type' => FacilityType::Clinic]);
        $this->withWorkUnit($centre, 'Итна Помош');

        $this->artisan('urgent-care:derive', ['--dry-run' => true])->assertSuccessful();

        $this->assertFalse($centre->refresh()->has_emergency_medical_service);
        $this->assertNull($centre->urgent_care_evidence);

        $this->artisan('urgent-care:derive')->assertSuccessful();

        $this->assertTrue($centre->refresh()->has_emergency_medical_service);
        $this->assertNull($pharmacy->refresh()->urgent_care_evidence);
    }

    public function test_the_fzom_import_derives_from_work_units(): void
    {
        Storage::fake('local');
        config(['import.disk' => 'local']);

        $extra = <<<'XML'
          <Lekar>
            <TipDogovor>Болничка здравствена заштита ЈЗУ (Општи болници)</TipDogovor>
            <TipDogovorID>16</TipDogovorID>
            <DanocenBroj>4000000000010</DanocenBroj>
            <ShifraZU>9000010</ShifraZU>
            <ZdravstvenaUstanova>ЈЗУ ОПШТА БОЛНИЦА ТЕСТОВО</ZdravstvenaUstanova>
            <RabotnaEdinica>УРГЕНТЕН ЦЕНТАР</RabotnaEdinica>
            <Specijalnosti>УРГЕНТНА МЕДИЦИНА</Specijalnosti>
            <Mesto>ТЕСТОВО</Mesto>
            <Faksimil>900077</Faksimil>
            <Ime>УРГЕНТНА</Ime>
            <Prezime>ТЕСТОВСКА</Prezime>
          </Lekar>
        </Lekari>
        XML;
        $spec = str_replace('</Lekari>', $extra, (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $path = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
        file_put_contents($path, $spec);

        $run = app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => $path,
        ]);

        $hospital = Facility::query()->where('fzo_code', '9000010')->firstOrFail();
        $this->assertTrue($hospital->has_emergency_services, (string) $run->error);
        $this->assertSame(1, $run->count('urgent_care_flags_set'));
        $this->assertFalse($hospital->is_published, 'Still a hidden draft.');
    }
}
