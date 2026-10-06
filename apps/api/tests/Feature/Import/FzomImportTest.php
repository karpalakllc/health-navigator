<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use App\Models\Specialty;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * import:fzom against small synthetic ФЗОМ files (tests/Fixtures/import/fzom).
 */
class FzomImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    /**
     * @return array<string, string>
     */
    private function files(?string $spec = null, ?string $pzz = null): array
    {
        return [
            'pzz' => $pzz ?? base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => $spec ?? base_path('tests/Fixtures/import/fzom/spec.xml'),
        ];
    }

    private function import(bool $dryRun = false, ?array $files = null): ImportRun
    {
        return app(FzomImportJob::class)->run($dryRun, null, $files ?? $this->files());
    }

    private function writeSpec(string $xml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
        file_put_contents($path, $xml);

        return $path;
    }

    public function test_it_imports_facilities_and_doctors_as_hidden_drafts(): void
    {
        $run = $this->import();

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(4, $run->count('facilities_created'));
        $this->assertSame(3, $run->count('doctors_created'));
        $this->assertSame(1, $run->count('dentists_created'));
        $this->assertSame(1, $run->count('rows_excluded_pharmacy'));
        $this->assertSame(1, $run->count('people_excluded_non_physician'));

        $this->assertSame(0, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(0, Facility::query()->where('is_published', true)->count());

        $doctor = Doctor::query()->where('fzo_facsimile', '900001')->firstOrFail();
        $this->assertSame('Првана Примеровска', $doctor->full_name);
        $this->assertSame('Тестово', $doctor->city);
        $this->assertSame(['opshta-medicina'], $doctor->specialties->pluck('slug')->all());
        $this->assertCount(2, $doctor->facilities);
        // The primary-care contract is the primary workplace.
        $this->assertSame('9000001', $doctor->facilities->firstWhere('pivot.is_primary', true)?->fzo_code);

        $dentist = Doctor::query()->where('fzo_facsimile', '900002')->firstOrFail();
        $this->assertSame(['stomatologija'], $dentist->specialties->pluck('slug')->all());

        $cardio = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $this->assertEqualsCanonicalizing(['interna-medicina', 'kardiologija'], $cardio->specialties->pluck('slug')->all());

        $hospital = Facility::query()->where('fzo_code', '9000010')->firstOrFail();
        $this->assertSame('hospital', $hospital->type->value);
        $this->assertSame('public', $hospital->ownership);
        $this->assertSame('ЈЗУ Општа Болница Тестово', $hospital->name);

        // Imported specialties are created hidden.
        $this->assertFalse((bool) Specialty::query()->where('slug', 'kardiologija')->value('is_published'));

        // Every new draft is in the review queue, plus the unmapped wording.
        $this->assertSame(8, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->count());
        $this->assertSame(1, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Unmatched)->count());
        $this->assertSame(1, $run->count('doctors_with_unmapped_specialty'));
        $this->assertNotNull($run->diff_path);
        Storage::disk('local')->assertExists((string) $run->diff_path);
    }

    public function test_excluded_fields_and_people_are_never_stored(): void
    {
        $run = $this->import();

        $stored = json_encode([
            DB::table('doctors')->get(),
            DB::table('facilities')->get(),
            DB::table('source_records')->get(),
            DB::table('import_review_items')->get(),
            DB::table('field_provenance')->get(),
            DB::table('doctor_facility')->get(),
        ], JSON_UNESCAPED_UNICODE).Storage::disk('local')->get((string) $run->diff_path);

        // Team nurse, substitutes, absence reason, pharmacist, psychologist.
        foreach (['НЕВИДЛИВА', 'Невидлива', 'ТАЈНА', 'АПТЕКАРОВА', 'Аптекарова', 'ДУШЕВНА', 'Душевна', '900099', 'АПТЕКА ТЕСТ'] as $needle) {
            $this->assertStringNotContainsString($needle, $stored, "\"{$needle}\" must not be stored.");
        }

        // The facsimile number is a matching key only, not in the staff diff.
        $this->assertStringNotContainsString('900001', (string) Storage::disk('local')->get((string) $run->diff_path));
    }

    public function test_a_second_run_with_the_same_files_changes_nothing(): void
    {
        $this->import();
        $counts = [Doctor::query()->count(), Facility::query()->count(), ImportReviewItem::query()->count(), DB::table('field_provenance')->count()];

        $second = $this->import();

        $this->assertSame(ImportRunStatus::Succeeded, $second->status);
        $this->assertSame(0, $second->count('doctors_created') + $second->count('facilities_created') + $second->count('fields_updated'));
        $this->assertSame(3 + 1, $second->count('doctors_unchanged'));
        $this->assertSame(4, $second->count('facilities_unchanged'));
        $this->assertSame($counts, [Doctor::query()->count(), Facility::query()->count(), ImportReviewItem::query()->count(), DB::table('field_provenance')->count()]);
    }

    public function test_a_dry_run_reports_the_same_counts_and_writes_nothing(): void
    {
        $dry = $this->import(dryRun: true);

        $this->assertSame(ImportRunStatus::Succeeded, $dry->status, (string) $dry->error);
        $this->assertTrue($dry->dry_run);
        $this->assertSame(3, $dry->count('doctors_created'));
        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, SourceRecord::query()->count());
        $this->assertSame(0, ImportReviewItem::query()->count());
        $this->assertSame(0, Specialty::query()->count());
        Storage::disk('local')->assertExists((string) $dry->diff_path);

        $apply = $this->import();
        $this->assertSame($dry->counts, $apply->counts);
    }

    public function test_a_value_staff_changed_is_not_overwritten_but_raised_as_a_conflict(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $doctor->update(['city' => 'Друг Град']);

        $spec = str_replace('ЧЕТВРТИ', 'ЧЕТВРТИНА', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $run = $this->import(files: $this->files($this->writeSpec(str_replace('<Mesto>ТЕСТОВО</Mesto>', '<Mesto>НОВО МЕСТО</Mesto>', $spec))));

        $doctor->refresh();
        $this->assertSame('Друг Град', $doctor->city, 'A staff edit must survive the import.');
        $this->assertSame('Четвртина Срцевски', $doctor->full_name, 'An untouched imported field follows the source.');
        $this->assertGreaterThanOrEqual(1, $run->count('fields_conflict'));
        $conflict = ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Conflict)
            ->where('item_key', 'doctor:'.$doctor->getKey().':city')->firstOrFail();
        $this->assertSame('Друг Град', $conflict->details['current']);
        $this->assertSame('Ново Место', $conflict->details['incoming']);
    }

    public function test_a_locked_field_is_left_alone_without_a_conflict(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        ProvenanceWriter::setLock($doctor, 'full_name', true, null);

        $spec = str_replace('ЧЕТВРТИ', 'ЧЕТВРТИНА', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $run = $this->import(files: $this->files($this->writeSpec($spec)));

        $this->assertSame('Четврти Срцевски', $doctor->refresh()->full_name);
        $this->assertSame(1, $run->count('fields_locked'));
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Conflict)->count());
    }

    public function test_a_row_without_facility_code_joins_the_one_coded_facility_with_its_tax_number(): void
    {
        $extra = <<<'XML'
          <Lekar>
            <TipDogovor>Болничка здравствена заштита ЈЗУ (Општи болници)</TipDogovor>
            <TipDogovorID>16</TipDogovorID>
            <DanocenBroj>4000000000010</DanocenBroj>
            <ZdravstvenaUstanova>ЈЗУ ОПШТА БОЛНИЦА ТЕСТОВО</ZdravstvenaUstanova>
            <RabotnaEdinica>ХИРУРГИЈА</RabotnaEdinica>
            <Specijalnosti>ОПШТА ХИРУРГИЈА</Specijalnosti>
            <Mesto>ТЕСТОВО</Mesto>
            <Faksimil>900013</Faksimil>
            <Ime>СЕДМИ</Ime>
            <Prezime>БЕЗШИФРОВ</Prezime>
          </Lekar>
        </Lekari>
        XML;
        $spec = str_replace('</Lekari>', $extra, (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $files = $this->files($this->writeSpec($spec));

        $run = $this->import(files: $files);

        $this->assertSame(1, $run->count('facilities_folded_by_tax_number'));
        $this->assertSame(4, Facility::query()->count());
        $doctor = Doctor::query()->where('fzo_facsimile', '900013')->firstOrFail();
        $this->assertSame(['9000010'], $doctor->facilities->pluck('fzo_code')->all());

        $second = $this->import(files: $files);
        $this->assertSame(0, $second->count('fields_updated'));
        $this->assertSame(0, $second->count('facilities_created'));
    }

    public function test_a_row_without_specialty_takes_one_from_the_work_unit_activity(): void
    {
        $extra = <<<'XML'
          <Lekar>
            <TipDogovor>Специјалистичко - консултативна здравствена заштита ЈЗУ (Здравствени домови)</TipDogovor>
            <TipDogovorID>12</TipDogovorID>
            <DanocenBroj>4000000000011</DanocenBroj>
            <ShifraZU>9000011</ShifraZU>
            <ZdravstvenaUstanova>ЈЗУ ЗДРАВСТВЕН ДОМ ТЕСТОВО</ZdravstvenaUstanova>
            <Dejnost>ДЕТСКИ БОЛЕСТИ</Dejnost>
            <Specijalnosti></Specijalnosti>
            <Mesto>ТЕСТОВО</Mesto>
            <Faksimil>900014</Faksimil>
            <Ime>ОСМА</Ime>
            <Prezime>ДЕТСКА</Prezime>
          </Lekar>
        </Lekari>
        XML;
        $spec = str_replace('</Lekari>', $extra, (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));

        $run = $this->import(files: $this->files($this->writeSpec($spec)));

        $this->assertSame(1, $run->count('doctors_specialty_from_activity'));
        $this->assertSame(['pedijatrija'], Doctor::query()->where('fzo_facsimile', '900014')->firstOrFail()->specialties->pluck('slug')->all());
    }

    public function test_a_link_staff_removed_is_not_added_back(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900001')->firstOrFail();
        $hospital = Facility::query()->where('fzo_code', '9000010')->firstOrFail();
        $doctor->facilities()->detach($hospital->getKey());

        // A changed row, so the record is re-applied rather than skipped as unchanged.
        $spec = str_replace('ОПШТА МЕДИЦИНА</Specijalnosti>', 'ОПШТА МЕДИЦИНА, СЕМЕЈНА МЕДИЦИНА</Specijalnosti>', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $this->import(files: $this->files($this->writeSpec($spec)));

        $doctor->refresh();
        $this->assertFalse($doctor->facilities->contains('id', $hospital->getKey()));
        $this->assertTrue($doctor->specialties->contains('slug', 'semejna-medicina'));
    }

    public function test_changes_to_a_published_profile_are_applied_and_listed_for_review(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $doctor->forceFill(['is_published' => true, 'published_at' => now()])->save();

        $spec = str_replace('ЧЕТВРТИ', 'ЧЕТВРТИНА', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml')));
        $this->import(files: $this->files($this->writeSpec($spec)));

        $this->assertSame('Четвртина Срцевски', $doctor->refresh()->full_name);
        $changed = ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Changed)->where('subject_id', $doctor->getKey())->firstOrFail();
        $this->assertSame('Четврти Срцевски', $changed->details['fields']['full_name']['old']);
        $this->assertTrue($doctor->is_published);
    }

    public function test_a_doctor_missing_from_two_runs_is_queued_and_never_deleted(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900012')->firstOrFail();
        $doctor->forceFill(['is_published' => true, 'published_at' => now()])->save();

        $xml = (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml'));
        $without = $this->writeSpec((string) preg_replace('/<Lekar>\s*<ArhivskiBroj>20-4\/1.*?<\/Lekar>/su', '', $xml));
        config(['import.max_missing_ratio' => 0.5]);

        $this->import(files: $this->files($without));
        $this->assertSame(1, $doctor->refresh()->import_missing_runs);
        $this->assertSame(0, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Missing)->count());

        $this->import(files: $this->files($without));
        $doctor->refresh();
        $this->assertSame(2, $doctor->import_missing_runs);
        $this->assertTrue($doctor->is_published, 'Missing never unpublishes by itself.');
        $this->assertFalse($doctor->trashed());
        $this->assertSame(1, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Missing)->where('subject_type', 'doctor')->where('subject_id', $doctor->getKey())->count());

        $this->import();
        $this->assertSame(0, $doctor->refresh()->import_missing_runs);
        $this->assertSame(0, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Missing)->count());
    }

    public function test_an_apply_stops_before_writing_when_most_doctors_vanish(): void
    {
        $this->import();
        $doctorCount = Doctor::query()->count();
        $empty = $this->writeSpec('<?xml version="1.0" encoding="utf-8"?><Lekari></Lekari>');

        $run = $this->import(files: $this->files($empty, $this->writeSpec((string) preg_replace('/<Lekar>\s*<ArhivskiBroj>10-1\/1.*?<\/Lekar>/su', '', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/pzz.xml'))))));

        $this->assertSame(ImportRunStatus::Failed, $run->status);
        $this->assertStringContainsString('Safety stop', (string) $run->error);
        $this->assertSame($doctorCount, Doctor::query()->count());
        $this->assertSame(0, Doctor::query()->where('import_missing_runs', '>', 0)->count());
    }

    public function test_an_existing_staff_profile_is_matched_by_name_and_workplace_instead_of_duplicated(): void
    {
        $facility = Facility::factory()->create(['fzo_code' => '9000010', 'name' => 'Општа болница Тестово', 'type' => 'hospital']);
        $existing = Doctor::factory()->create(['full_name' => 'Д-р Четврти Срцевски', 'city' => 'Тестово', 'is_published' => true]);
        $existing->facilities()->attach($facility->getKey(), ['is_primary' => true]);

        $run = $this->import();

        $this->assertSame(1, $run->count('doctors_matched'));
        $this->assertSame('900010', $existing->refresh()->fzo_facsimile);
        $this->assertSame(1, Doctor::query()->where('name_key', 'ЧЕТВРТИ СРЦЕВСКИ')->count());
        // The staff-set name is someone else's value: kept, raised as a conflict.
        $this->assertSame('Д-р Четврти Срцевски', $existing->full_name);
        // The staff link stays the primary one and is not taken over.
        $this->assertNull(DB::table('doctor_facility')->where('doctor_id', $existing->getKey())->where('facility_id', $facility->getKey())->value('source'));
    }

    public function test_two_existing_profiles_with_the_same_name_are_not_guessed(): void
    {
        $facility = Facility::factory()->create(['fzo_code' => '9000010', 'type' => 'hospital']);

        foreach ([1, 2] as $_) {
            Doctor::factory()->create(['full_name' => 'Четврти Срцевски'])->facilities()->attach($facility->getKey());
        }

        $run = $this->import();

        $this->assertSame(1, $run->count('doctors_ambiguous'));
        $this->assertSame(0, Doctor::query()->where('fzo_facsimile', '900010')->count());
        $this->assertSame(1, ImportReviewItem::query()->where('kind', ImportReviewKind::Unmatched)->where('title', 'like', '%Срцевски%')->count());
    }
}
