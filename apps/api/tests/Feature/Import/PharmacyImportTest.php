<?php

namespace Tests\Feature\Import;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\PharmacyDutyShift;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\Pharmacies\FzomPharmacyReader;
use App\Support\Import\Pharmacies\OnDutyLinker;
use App\Support\Import\Pharmacies\OnDutyNames;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use App\Support\Import\Pharmacies\PharmacyImportJob;
use App\Support\Import\Pharmacies\PharmacyRegisterImporter;
use App\Support\Verification\Engine\VerificationEngine;
use App\Support\Verification\VerificationBasis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * import:pharmacies against a small synthetic ФЗОМ file
 * (tests/Fixtures/import/pharmacies/pzz.xml), docs/data-import.md §4a.
 */
class PharmacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local', 'import.verification.after_import' => false]);
    }

    private function fixture(): string
    {
        return base_path('tests/Fixtures/import/pharmacies/pzz.xml');
    }

    private function import(bool $dryRun = false, ?string $file = null): ImportRun
    {
        return app(PharmacyImportJob::class)->run($dryRun, null, ['pzz' => $file ?? $this->fixture()]);
    }

    private function pharmacy(string $code): Facility
    {
        return Facility::query()->where('fzo_code', $code)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function shift(string $town, string $name, array $overrides = []): PharmacyDutyShift
    {
        $run = ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null);

        return PharmacyDutyShift::query()->create([
            'month' => '2026-10',
            'duty_date' => '2026-10-07',
            'town' => $town,
            'town_key' => OnDutyNames::townKey($town),
            'pharmacy_name' => $name,
            'name_key' => OnDutyNames::pharmacyKey($name, $town),
            'phone' => '047/236-468',
            'mode' => PharmacyDutyShift::MODE_HOURS,
            'import_run_id' => $run->getKey(),
            ...$overrides,
        ]);
    }

    public function test_the_reader_folds_pharmacists_into_branches_and_reads_no_person(): void
    {
        $reader = new FzomPharmacyReader;
        $branches = $reader->branches($this->fixture(), 'pzz');

        $this->assertSame(['8800101', '8800201', '8800202', '8800301'], array_map('strval', array_keys($branches)));
        $this->assertSame('2027-06-30', $branches['8800101']['valid_to']);
        $this->assertSame('ПРИВАТНА ЗДРАВСТВЕНА УСТАНОВА-АПТЕКА MОЈА АПТЕКА СКОПЈЕ-ПОДРУЖНИЦА ТЕСТОВО 3', $branches['8800201']['branch']);

        $kept = tempnam(sys_get_temp_dir(), 'pharm');
        $reader->minimise($this->fixture(), $kept, 'pzz');
        $xml = (string) file_get_contents($kept);

        foreach (['ПРВАКОВА', 'ФАРМАЦЕВТОВА', '700001', 'Faksimil', 'Ime', 'ClenNaTim', 'ДОКТОРОВ', 'ОРДИНАЦИЈА'] as $absent) {
            $this->assertStringNotContainsString($absent, $xml);
        }

        // The snapshot reads back to the same branches.
        $this->assertSame($branches, $reader->branches($kept, 'pzz'));
    }

    public function test_it_creates_one_hidden_draft_pharmacy_per_branch_with_provenance(): void
    {
        $run = $this->import();

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(4, $run->count('facilities_in_source'));
        $this->assertSame(4, $run->count('facilities_created'));
        $this->assertSame(0, Facility::query()->where('is_published', true)->count());

        $rosa = $this->pharmacy('8800101');
        $this->assertSame(FacilityType::Pharmacy, $rosa->type);
        $this->assertSame('Аптека Роса Вита', $rosa->name);
        $this->assertSame('Тестово', $rosa->city);
        // Cased like every ФЗОМ address (TextCase::place).
        $this->assertSame('Ул.Маршал Тито БР.16', $rosa->address);
        $this->assertSame('4000000000101', $rosa->tax_number);
        $this->assertSame('private', $rosa->ownership);
        $this->assertSame(PharmacyRegisterImporter::SOURCE, $rosa->import_source);
        $this->assertFalse($rosa->is_published);

        $this->assertSame('Моја Аптека Тестово 3', $this->pharmacy('8800201')->name);
        $this->assertSame('Скопје - Карпош', $this->pharmacy('8800202')->city);

        $this->assertSame(PharmacyRegisterImporter::SOURCE, FieldProvenance::query()
            ->where('subject_type', 'facility')->where('subject_id', $rosa->id)->where('field', 'name')->value('source'));
        $this->assertSame(4, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->where('source', PharmacyRegisterImporter::SOURCE)->count());

        // No person reaches the database.
        $payloads = SourceRecord::query()->where('source', PharmacyRegisterImporter::SOURCE)->pluck('payload')->toJson(JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('ПРВАКОВА', $payloads);
        $this->assertStringNotContainsString('700001', $payloads);
    }

    public function test_a_second_run_of_the_same_file_changes_nothing(): void
    {
        $this->import();
        $before = Facility::query()->orderBy('id')->get(['id', 'name', 'updated_at'])->toArray();

        $second = $this->import();

        $this->assertSame(4, $second->count('facilities_unchanged'));
        $this->assertSame(0, $second->count('facilities_created'));
        $this->assertSame(0, $second->count('review_new'));
        $this->assertSame($before, Facility::query()->orderBy('id')->get(['id', 'name', 'updated_at'])->toArray());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $run = $this->import(dryRun: true);

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(4, $run->count('facilities_created'));
        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, SourceRecord::query()->count());
        $this->assertSame(0, ImportReviewItem::query()->count());
    }

    public function test_it_takes_over_a_pharmacy_staff_entered_and_never_guesses_between_two(): void
    {
        $handMade = Facility::factory()->pharmacy()->create(['name' => 'ПЗУ Аптека „Зегин Фарм“', 'city' => 'Тестово', 'address' => null, 'phone' => '02 111 222']);
        $twinA = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса Вита', 'city' => 'Тестово', 'address' => null]);
        $twinB = Facility::factory()->pharmacy()->create(['name' => 'Роса Вита', 'city' => 'Тестово - Центар', 'address' => null]);

        $run = $this->import();

        $handMade->refresh();
        $this->assertSame('8800301', $handMade->fzo_code);
        // Someone else's name is not overwritten: a conflict for staff.
        $this->assertSame('ПЗУ Аптека „Зегин Фарм“', $handMade->name);
        $this->assertSame('02 111 222', $handMade->phone);
        $this->assertTrue(ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Conflict)->where('subject_id', $handMade->id)->exists());

        $this->assertSame(1, $run->count('pharmacies_ambiguous'));
        $this->assertNull(Facility::query()->where('fzo_code', '8800101')->first());
        $item = ImportReviewItem::query()->where('item_key', 'fzom-pharmacy:8800101')->firstOrFail();
        $this->assertEqualsCanonicalizing([$twinA->id, $twinB->id], $item->details['candidate_facility_ids']);
    }

    public function test_a_hand_entered_pharmacy_on_another_street_is_not_taken_for_the_branch(): void
    {
        $other = Facility::factory()->pharmacy()->create(['name' => 'ПЗУ Аптека „Зегин Фарм“', 'city' => 'Тестово', 'address' => 'Илинденска 5']);

        $this->import();

        $this->assertNull($other->fresh()->fzo_code);
        $this->assertNotNull(Facility::query()->where('fzo_code', '8800301')->first());
        $this->assertNotSame($other->id, Facility::query()->where('fzo_code', '8800301')->value('id'));
    }

    public function test_the_schedule_phone_never_replaces_or_disputes_a_phone_someone_entered(): void
    {
        $handMade = Facility::factory()->pharmacy()->create(['name' => 'ПЗУ Аптека „Зегин Фарм“', 'city' => 'Тестово', 'address' => null, 'phone' => '02 111 222']);
        $shift = $this->shift('Тестово', 'ПЗУ Аптека „Зегин Фарм“ Тестово', ['phone' => '070 999 888']);

        $this->import();

        $this->assertSame($handMade->id, $shift->fresh()->facility_id);
        $this->assertSame('02 111 222', $handMade->fresh()->phone);
        $this->assertFalse(ImportReviewItem::query()->where('kind', ImportReviewKind::Conflict)->where('subject_id', $handMade->id)->where('details->field', 'phone')->exists());
    }

    public function test_a_code_held_by_another_kind_of_facility_or_a_deleted_pharmacy_is_not_taken_over(): void
    {
        $clinic = Facility::factory()->create(['fzo_code' => '8800202', 'type' => FacilityType::Clinic]);
        $deleted = Facility::factory()->pharmacy()->create(['fzo_code' => '8800301']);
        $deleted->delete();

        $run = $this->import();

        $this->assertSame(1, $run->count('pharmacies_code_on_other_facility'));
        $this->assertSame(1, $run->count('pharmacies_deleted_by_staff'));
        $this->assertSame(FacilityType::Clinic, $clinic->fresh()->type);
        $this->assertSame(1, Facility::withTrashed()->where('fzo_code', '8800301')->count());
        $this->assertTrue(ImportReviewItem::query()->open()->where('item_key', 'fzom-pharmacy:8800202')->where('details->reason', 'code_on_other_facility')->exists());
    }

    public function test_the_on_duty_schedule_is_linked_and_fills_the_phone(): void
    {
        $byName = $this->shift('Тестово', 'ПЗУ Аптека „Роса Вита“ Тестово');
        $byLooseName = $this->shift('Тестово', 'Моја Аптека 3 Тестово', ['phone' => '070 111 333']);
        $variant = $this->shift('Тестово', 'ЗЕГИН1');
        $suggested = $this->shift('Тестово', 'Роса');
        $skopje = $this->shift('Скопје', 'Моја 2');
        $unknown = $this->shift('Тестово', 'Непозната');

        $run = $this->import();

        $rosa = $this->pharmacy('8800101');
        $this->assertSame($rosa->id, $byName->fresh()->facility_id);
        $this->assertSame('047/236-468', $rosa->phone);
        $this->assertSame(OnDutyPharmacyImporter::SOURCE, FieldProvenance::query()->where('subject_id', $rosa->id)->where('field', 'phone')->value('source'));
        $this->assertSame($this->pharmacy('8800201')->id, $byLooseName->fresh()->facility_id);
        $this->assertSame($this->pharmacy('8800202')->id, $skopje->fresh()->facility_id);
        $this->assertSame($this->pharmacy('8800301')->id, $variant->fresh()->facility_id);
        $this->assertNull($suggested->fresh()->facility_id);
        $this->assertNull($unknown->fresh()->facility_id);
        $this->assertSame(4, $run->count('duty_pharmacies_matched'));
        $this->assertSame(1, $run->count('duty_pharmacies_matched_by_name_variant'));
        $this->assertSame(1, $run->count('duty_pharmacies_suggested'));

        $item = ImportReviewItem::query()->open()->where('source', OnDutyPharmacyImporter::SOURCE)->where('details->reason', OnDutyLinker::REASON_SUGGESTED)->firstOrFail();
        $this->assertSame($this->pharmacy('8800101')->id, $item->details['suggested_facility_id']);
        $this->assertTrue(ImportReviewItem::query()->open()->where('details->reason', OnDutyLinker::REASON_MISSING)->where('details->name', 'Непозната')->exists());

        // Staff accept the suggestion: every month linked, remembered.
        $staff = User::factory()->create();
        $this->assertTrue(app(OnDutyLinker::class)->linkByStaff($item, (int) $item->details['suggested_facility_id'], $staff));
        $this->assertSame($this->pharmacy('8800101')->id, $suggested->fresh()->facility_id);
        $this->assertSame(ImportReviewStatus::Resolved, $item->fresh()->status);

        // The next schedule import keeps the link (alias) and queues nothing new.
        $context = new ImportContext(ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null), OnDutyPharmacyImporter::SOURCE, false);
        (new OnDutyPharmacyImporter)->import($context, [
            'month' => '2026-11',
            'rows' => [['line' => 1, 'town' => 'Тестово', 'municipality' => null, 'name' => 'Роса', 'days' => [3], 'phone' => null, 'mode' => 'hours', 'hours_text' => null, 'address' => null]],
            'problems' => [],
        ]);
        $this->assertSame(1, $context->count('pharmacies_matched_by_alias'));
        $this->assertSame($this->pharmacy('8800101')->id, PharmacyDutyShift::query()->where('month', '2026-11')->value('facility_id'));
    }

    public function test_a_pharmacy_in_the_register_is_verified_and_a_renamed_one_is_not(): void
    {
        $this->import();
        $renamed = $this->pharmacy('8800201');
        $renamed->forceFill(['name' => 'Сосема Друга Аптека'])->saveQuietly();
        $handMade = Facility::factory()->pharmacy()->create(['name' => 'Аптека Рачна', 'city' => 'Битола']);

        app(VerificationEngine::class)->run();

        $rosa = $this->pharmacy('8800101');
        $this->assertNotNull($rosa->verified_at);
        $this->assertSame(VerificationBasis::OfficialRegisters, $rosa->verification_basis);
        $this->assertNull($renamed->fresh()->verified_at);
        $this->assertSame('register_mismatch', $renamed->fresh()->verification_reasons['reason']);
        $this->assertSame('no_pharmacy_register', $handMade->fresh()->verification_reasons['reason']);
    }

    public function test_a_branch_gone_from_two_runs_is_queued_as_missing_and_a_truncated_file_stops_the_run(): void
    {
        config(['import.max_missing_ratio' => 0.5]);
        $this->import();
        $three = $this->fixtureWithout(['8800301']);

        $this->import(file: $three);
        $this->import(file: $three);

        $zegin = $this->pharmacy('8800301');
        $this->assertSame(2, $zegin->import_missing_runs);
        $this->assertTrue(ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Missing)->where('subject_id', $zegin->id)->exists());

        $one = $this->fixtureWithout(['8800201', '8800202', '8800301']);
        $stopped = $this->import(file: $one);

        $this->assertSame(ImportRunStatus::Failed, $stopped->status);
        $this->assertStringContainsString('Safety stop', (string) $stopped->error);
        $this->assertSame(0, $this->pharmacy('8800201')->import_missing_runs);
    }

    public function test_the_command_downloads_politely_and_keeps_only_the_minimised_snapshot(): void
    {
        config(['import.request_delay_seconds' => 0, 'import.ca_bundle' => '', 'import.contact' => 'imports@example.invalid']);
        Http::fake([
            'https://arhiva.fzo.org.mk/robots.txt' => Http::response('', 404),
            'https://arhiva.fzo.org.mk/XML/LekariLista_SitePzz.xml' => Http::response((string) file_get_contents($this->fixture()), 200, ['ETag' => '"v1"']),
        ]);

        $this->artisan('import:pharmacies')->assertSuccessful();

        Http::assertSent(fn ($request): bool => str_contains($request->header('User-Agent')[0] ?? '', '+mailto:imports@example.invalid'));
        $this->assertSame(4, Facility::query()->pharmacy()->count());
        $files = Storage::disk('local')->allFiles('imports/snapshots/'.PharmacyRegisterImporter::SOURCE);
        $snapshot = collect($files)->first(fn (string $path): bool => str_ends_with($path, '.xml'));
        $this->assertNotNull($snapshot);
        $this->assertStringNotContainsString('ПРВАКОВА', Storage::disk('local')->get($snapshot));
    }

    public function test_the_command_is_switched_off_by_config(): void
    {
        config(['import.pharmacies.enabled' => false]);

        $this->artisan('import:pharmacies', ['--file' => $this->fixture()])->assertSuccessful();

        $this->assertSame(0, Facility::query()->count());
    }

    /**
     * @param  list<string>  $codes
     */
    private function fixtureWithout(array $codes): string
    {
        $xml = (string) file_get_contents($this->fixture());

        foreach ($codes as $code) {
            $xml = (string) preg_replace('#\s*<Lekar>(?:(?!</Lekar>).)*<ShifraZU>'.$code.'</ShifraZU>.*?</Lekar>#su', '', $xml);
        }

        $path = tempnam(sys_get_temp_dir(), 'pharm').'.xml';
        file_put_contents($path, $xml);

        return $path;
    }
}
