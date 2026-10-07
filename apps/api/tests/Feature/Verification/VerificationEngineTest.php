<?php

namespace Tests\Feature\Verification;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\KomoraLicence;
use App\Models\LicenceSpecialtyMapping;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Import\NameKey;
use App\Support\Licences\SpecialtyKey;
use App\Support\Verification\Engine\EvidenceLoader;
use App\Support\Verification\Engine\VerificationEngine;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The verification engine on small synthetic fixtures (invented names): a
 * profile is verified only when two independent sources agree, everything
 * else stays unverified with a reason, and only genuinely uncertain cases
 * reach the review queue — grouped.
 */
class VerificationEngineTest extends TestCase
{
    use RefreshDatabase;

    private int $records = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00'));

        // The latest complete ФЗОМ snapshot started an hour ago.
        ImportRun::query()->create([
            'source' => 'fzom', 'dry_run' => false, 'status' => ImportRunStatus::Succeeded,
            'started_at' => now()->subHour(), 'finished_at' => now()->subMinutes(50), 'source_meta' => ['complete' => true],
        ]);
    }

    private function adjudicate(bool $dryRun = false): ImportRun
    {
        $run = app(VerificationEngine::class)->run($dryRun);
        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);

        return $run;
    }

    private function specialty(string $name): Specialty
    {
        return Specialty::query()->firstOrCreate(['slug' => 'test-'.mb_strtolower(md5($name))], ['name' => $name]);
    }

    private function facility(string $name = 'ЈЗУ Тест Болница', string $city = 'Скопје', string $tax = '4030000000001'): Facility
    {
        $facility = Facility::factory()->unpublished()->create(['name' => $name, 'city' => $city, 'type' => FacilityType::Hospital]);
        $facility->forceFill(['tax_number' => $tax, 'import_source' => 'fzom'])->saveQuietly();
        $this->record('fzom', 'facility:edb:'.$tax.':'.NameKey::for($city), FieldProvenance::SUBJECT_FACILITY, $facility->id, ['name' => mb_strtoupper($name), 'town' => $city]);

        return $facility;
    }

    /**
     * A ФЗОМ doctor: facsimile, a current source record, a workplace and a specialty, all written by the ФЗОМ import.
     */
    private function fzomDoctor(string $name, Facility $facility, string $specialty = 'ПЕДИЈАТАР', bool $published = false): Doctor
    {
        $doctor = Doctor::factory()->create(['full_name' => $name, 'is_published' => $published, 'published_at' => $published ? now() : null]);
        $doctor->forceFill(['fzo_facsimile' => (string) (100000 + $doctor->id), 'import_source' => 'fzom'])->saveQuietly();
        $this->record('fzom', 'doctor:'.(100000 + $doctor->id), FieldProvenance::SUBJECT_DOCTOR, $doctor->id, ['name' => $name]);
        DB::table('doctor_facility')->insert(['doctor_id' => $doctor->id, 'facility_id' => $facility->id, 'is_primary' => true, 'source' => 'fzom', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('doctor_specialty')->insert(['doctor_id' => $doctor->id, 'specialty_id' => $this->specialty($specialty)->id, 'is_primary' => true, 'source' => 'fzom', 'created_at' => now(), 'updated_at' => now()]);

        return $doctor;
    }

    /**
     * A draft only an institution's staff page lists.
     */
    private function websiteDoctor(string $name, Facility $facility, ?string $specialty = 'ПЕДИЈАТАР', string $confidence = 'high'): Doctor
    {
        $doctor = Doctor::factory()->unpublished()->create(['full_name' => $name]);
        $doctor->forceFill(['import_source' => 'website'])->saveQuietly();
        $this->websiteEntry($doctor, $facility, $specialty, $confidence);

        if ($specialty !== null) {
            DB::table('doctor_specialty')->insert(['doctor_id' => $doctor->id, 'specialty_id' => $this->specialty($specialty)->id, 'is_primary' => true, 'source' => 'website', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $doctor;
    }

    private function websiteEntry(Doctor $doctor, Facility $facility, ?string $specialty = 'ПЕДИЈАТАР', string $confidence = 'high', bool $link = true): void
    {
        $this->record('website', 'worker:'.$facility->id.':'.NameKey::for($doctor->full_name), FieldProvenance::SUBJECT_DOCTOR, $doctor->id, [
            'full_name' => $doctor->full_name, 'role' => 'physician', 'specialty' => $specialty, 'confidence' => $confidence,
            'specialty_ids' => $specialty !== null ? [$this->specialty($specialty)->id] : [],
        ]);

        if ($link && ! DB::table('doctor_facility')->where('doctor_id', $doctor->id)->where('facility_id', $facility->id)->exists()) {
            DB::table('doctor_facility')->insert(['doctor_id' => $doctor->id, 'facility_id' => $facility->id, 'is_primary' => false, 'source' => 'website', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * @param  list<string>  $flags
     */
    private function website(Facility $facility, array $flags = []): void
    {
        $this->record('website', 'institution:test:'.$facility->id, FieldProvenance::SUBJECT_FACILITY, $facility->id, [
            'name_mk' => $facility->name, 'source_flags' => $flags, 'source_flag_note' => $flags === [] ? null : 'Site carries injected casino spam posts.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $source, string $key, string $type, int $subjectId, array $payload, ?CarbonImmutable $seen = null): void
    {
        DB::table('source_records')->insert([
            'source' => $source, 'external_key' => $key, 'subject_type' => $type, 'subject_id' => $subjectId,
            'payload' => json_encode($payload), 'hash' => (string) ++$this->records,
            'first_seen_at' => now()->subMonth(), 'last_seen_at' => $seen ?? now()->subMinutes(55),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A row of the Комора list (staging) — attached to $doctor when given, as the licence import does.
     */
    private function licence(string $name, string $specialty, ?Doctor $doctor = null, string $validUntil = '2030-01-01', ?string $outcome = null, array $candidates = []): KomoraLicence
    {
        static $number = 1000;
        $number++;
        $licence = KomoraLicence::query()->create([
            'licence_number' => str_pad((string) $number, 7, '0', STR_PAD_LEFT),
            'full_name' => mb_strtoupper($name), 'name_key' => NameKey::for($name),
            'specialty' => $specialty, 'specialty_key' => SpecialtyKey::for($specialty),
            'valid_until' => $validUntil, 'list_date' => '2026-07-02',
            'first_seen_at' => now(), 'last_seen_at' => now(),
            'outcome' => $doctor !== null ? 'attached' : ($outcome ?? 'no_match'),
            'doctor_id' => $doctor?->id,
            'candidate_doctor_ids' => $doctor !== null ? [$doctor->id] : ($candidates === [] ? null : $candidates),
        ]);

        $doctor?->forceFill([
            'licence_number' => $licence->licence_number, 'licence_valid_until' => $validUntil,
            'licence_specialty_raw' => $specialty, 'licence_source' => 'komora', 'licence_checked_at' => now(),
        ])->saveQuietly();

        return $licence;
    }

    /**
     * @return array<string, mixed>
     */
    private function reasons(Doctor|Facility $subject): array
    {
        return (array) $subject->fresh()?->verification_reasons;
    }

    /**
     * @return list<ImportReviewItem>
     */
    private function uncertain(?string $reason = null): array
    {
        return ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Uncertain)->get()
            ->filter(fn (ImportReviewItem $item): bool => $reason === null || ($item->details['reason'] ?? null) === $reason)
            ->values()->all();
    }

    public function test_a_fzom_doctor_with_a_fitting_valid_licence_is_verified_on_official_registers(): void
    {
        $doctor = $this->fzomDoctor('Ана Тестовска', $this->facility());
        $licence = $this->licence('Ана Тестовска', 'педијатрија', $doctor);

        $run = $this->adjudicate();

        $doctor->refresh();
        $this->assertTrue($doctor->isVerified());
        $this->assertSame(VerificationBasis::OfficialRegisters, $doctor->verification_basis);
        $this->assertSame('fzom_licence', $doctor->verification_reasons['evidence'][0]['rule']);
        $this->assertSame($licence->id, $doctor->verification_reasons['evidence'][0]['komora_licence_id']);
        $this->assertSame(1, $run->counts['doctors_verified.fzom_licence']);
        // Internal keys never reach the evidence.
        $this->assertStringNotContainsString($licence->licence_number, (string) json_encode($doctor->verification_reasons));
        $this->assertStringNotContainsString((string) $doctor->fzo_facsimile, (string) json_encode($doctor->verification_reasons));
        $this->assertSame([], $this->uncertain());
        $this->assertFalse($doctor->is_published, 'The engine never publishes on its own.');
    }

    public function test_one_source_is_not_enough(): void
    {
        $facility = $this->facility();
        $noLicence = $this->fzomDoctor('Борис Безлиценцов', $facility, 'ОФТАЛМОЛОГИЈА');
        // A dentist only an institution's staff page lists (no ФЗОМ contract).
        $dentist = $this->websiteDoctor('Вера Забарска', $facility, 'Стоматологија');
        DB::table('specialties')->where('name', 'Стоматологија')->update(['slug' => 'stomatologija']);
        $websiteOnly = $this->websiteDoctor('Гоце Сајтовски', $facility);
        $handMade = Doctor::factory()->create(['full_name' => 'Дафина Рачна']);

        $this->adjudicate();

        $this->assertSame('fzom_no_licence', $this->reasons($noLicence)['reason']);
        $this->assertSame('dentist_single_source', $this->reasons($dentist)['reason']);
        $this->assertSame('no_licence', $this->reasons($websiteOnly)['reason']);
        $this->assertSame('no_import_evidence', $this->reasons($handMade)['reason']);
        $this->assertFalse($noLicence->fresh()->isVerified() || $dentist->fresh()->isVerified() || $websiteOnly->fresh()->isVerified() || $handMade->fresh()->isVerified());
        $this->assertSame([], $this->uncertain(), 'Missing evidence is not a question for staff.');
    }

    public function test_a_current_fzom_contract_alone_verifies_a_dentist_until_they_leave_fzom(): void
    {
        $facility = $this->facility();
        $dentist = $this->fzomDoctor('Вероника Стоматолошка', $facility, 'Стоматологија', published: true);
        DB::table('specialties')->where('name', 'Стоматологија')->update(['slug' => 'stomatologija']);
        $specialistDentist = $this->fzomDoctor('Горан Ортодонтски', $facility, 'Ортодонција');
        DB::table('specialties')->where('name', 'Ортодонција')->update(['slug' => 'stomatologija-ortodoncija']);

        $run = $this->adjudicate();

        $this->assertSame(VerificationBasis::OfficialRegisters, $dentist->fresh()->verification_basis);
        $this->assertSame('fzom_dentist', $dentist->fresh()->verification_reasons['evidence'][0]['rule']);
        $this->assertTrue($specialistDentist->fresh()->isVerified());
        $this->assertSame(2, $run->counts['doctors_verified.fzom_dentist']);
        app()->setLocale('mk');
        $this->assertSame('Регистар на ФЗОМ', $dentist->fresh()->publicVerification()['basis_label']);
        $this->assertSame([], $this->uncertain());

        // Gone from the latest ФЗОМ snapshot: the badge goes by itself.
        DB::table('doctors')->where('id', $dentist->id)->update(['import_missing_runs' => 1]);
        $this->adjudicate();

        $this->assertFalse($dentist->fresh()->isVerified());
        $this->assertSame('source_removed', $this->reasons($dentist)['reason']);
        $this->assertSame('official_registers', $this->reasons($dentist)['previous_basis']);
        $this->assertCount(1, $this->uncertain('verification_lost'), 'A public dentist that lost the badge is for staff.');
        $this->assertTrue($specialistDentist->fresh()->isVerified());
    }

    public function test_an_expired_licence_or_a_doctor_gone_from_fzom_loses_the_verification_and_a_public_profile_is_flagged(): void
    {
        $facility = $this->facility();
        $expiring = $this->fzomDoctor('Ема Истечена', $facility, published: true);
        $licence = $this->licence('Ема Истечена', 'педијатрија', $expiring, validUntil: '2026-10-20');
        $leaving = $this->fzomDoctor('Жарко Заминат', $facility);
        $this->licence('Жарко Заминат', 'педијатрија', $leaving);

        $this->adjudicate();
        $this->assertTrue($expiring->fresh()->isVerified());
        $this->assertTrue($leaving->fresh()->isVerified());

        $this->travel(20)->days();
        DB::table('doctors')->where('id', $leaving->id)->update(['import_missing_runs' => 1]);
        $this->adjudicate();

        $this->assertFalse($expiring->fresh()->isVerified());
        $this->assertSame('licence_expired', $this->reasons($expiring)['reason']);
        $this->assertSame('official_registers', $this->reasons($expiring)['previous_basis']);
        $this->assertFalse($leaving->fresh()->isVerified());
        $this->assertSame('source_removed', $this->reasons($leaving)['reason']);

        // Only the public one is a question for staff.
        $lost = $this->uncertain('verification_lost');
        $this->assertCount(1, $lost);
        $this->assertSame($expiring->id, (int) $lost[0]->subject_id);
        $this->assertGreaterThanOrEqual(1000, $lost[0]->priority);

        // Back on a fresh list: verified again, the item closes itself.
        $licence->update(['valid_until' => '2031-01-01']);
        $expiring->forceFill(['licence_valid_until' => '2031-01-01'])->saveQuietly();
        $this->adjudicate();
        $this->assertTrue($expiring->fresh()->isVerified());
        $this->assertSame([], $this->uncertain('verification_lost'));
        $this->assertSame('no_longer_applies', ImportReviewItem::query()->find($lost[0]->id)?->resolution);
    }

    public function test_a_licence_off_the_list_removes_the_verification(): void
    {
        $doctor = $this->fzomDoctor('Зоран Отпаднат', $this->facility());
        $licence = $this->licence('Зоран Отпаднат', 'педијатрија', $doctor);
        $this->adjudicate();
        $this->assertTrue($doctor->fresh()->isVerified());

        $licence->update(['missing_since' => '2026-10-01']);
        $this->adjudicate();

        $this->assertFalse($doctor->fresh()->isVerified());
        $this->assertSame('licence_off_list', $this->reasons($doctor)['reason']);
    }

    public function test_a_website_listing_and_a_licence_nobody_else_on_the_list_holds_verify_a_draft(): void
    {
        $facility = $this->facility();
        $this->website($facility);
        $unique = $this->websiteDoctor('Ивана Единствена', $facility);
        $this->licence('Ивана Единствена', 'педијатрија', $unique);
        $namesake = $this->websiteDoctor('Јана Двојна', $facility);
        $this->licence('Јана Двојна', 'педијатрија', $namesake);
        $this->licence('Јана Двојна', 'офталмологија');
        $uncertain = $this->websiteDoctor('Кире Можеби', $facility, confidence: 'medium');
        $this->licence('Кире Можеби', 'педијатрија', $uncertain);

        $this->adjudicate();

        $this->assertSame(VerificationBasis::LicenceAndWebsite, $unique->fresh()->verification_basis);
        // Another licence of the same name on the list: the page alone cannot tell them apart.
        $this->assertFalse($namesake->fresh()->isVerified());
        $this->assertSame('ambiguous_name', $this->reasons($namesake)['reason']);
        $this->assertFalse($uncertain->fresh()->isVerified());
    }

    public function test_fzom_and_the_staff_page_of_the_same_institution_verify_together(): void
    {
        $facility = $this->facility();
        $other = $this->facility('ЈЗУ Друга Болница', 'Битола', '4030000000002');
        $agreeing = $this->fzomDoctor('Лила Двоизворна', $facility);
        $this->websiteEntry($agreeing, $facility);
        $elsewhere = $this->fzomDoctor('Марко Наспроти', $facility);
        $this->websiteEntry($elsewhere, $other);
        $contradicting = $this->fzomDoctor('Нада Спротивна', $facility);
        $this->websiteEntry($contradicting, $facility, 'ОФТАЛМОЛОГИЈА');

        $this->adjudicate();

        $this->assertSame(VerificationBasis::WebsiteAndRegister, $agreeing->fresh()->verification_basis);
        $this->assertSame('sources_disagree', $this->reasons($elsewhere)['reason']);
        $this->assertSame('sources_disagree', $this->reasons($contradicting)['reason']);
    }

    public function test_a_flagged_website_is_asked_about_once_and_trusting_it_verifies_its_doctors(): void
    {
        $facility = $this->facility();
        $this->website($facility, ['compromised']);
        $first = $this->websiteDoctor('Оливер Сомнителен', $facility);
        $this->licence('Оливер Сомнителен', 'педијатрија', $first);
        $second = $this->websiteDoctor('Петра Сомнителна', $facility);
        $this->licence('Петра Сомнителна', 'педијатрија', $second);

        $this->adjudicate();

        $this->assertFalse($first->fresh()->isVerified());
        $this->assertSame('stale_source', $this->reasons($first)['reason']);
        $items = $this->uncertain('flagged_source');
        $this->assertCount(1, $items, 'One item per site, not per doctor.');
        $this->assertSame(2, $items[0]->details['doctors']);
        $this->assertSame(['compromised'], $items[0]->details['flags']);

        $items[0]->resolve(ImportReviewStatus::Resolved, EvidenceLoader::TRUSTED_RESOLUTION, User::factory()->create());
        $this->adjudicate();

        $this->assertSame(VerificationBasis::LicenceAndWebsite, $first->fresh()->verification_basis);
        $this->assertTrue($second->fresh()->isVerified());
        $this->assertSame([], $this->uncertain('flagged_source'));
    }

    public function test_a_specialty_pair_that_never_fits_is_one_item_and_a_mapping_fix_settles_every_doctor(): void
    {
        $facility = $this->facility();
        $first = $this->fzomDoctor('Ружа Мапирана', $facility);
        $second = $this->fzomDoctor('Сашо Мапиран', $facility);
        $this->licence('Ружа Мапирана', 'офталмологија', $first);
        $this->licence('Сашо Мапиран', 'офталмологија', $second);
        // A general doctor's licence on a specialist profile contradicts the
        // profile: not a mapping question, no item.
        $resident = $this->fzomDoctor('Тони Специјализант', $facility);
        $this->licence('Тони Специјализант', 'доктор на медицина во ПЗЗ', $resident);

        $this->adjudicate();

        $this->assertSame('specialty_mismatch', $this->reasons($first)['reason']);
        $this->assertSame('specialty_mismatch', $this->reasons($resident)['reason']);
        $items = $this->uncertain('specialty_mapping');
        $this->assertCount(1, $items);
        $this->assertSame(2, $items[0]->details['doctors']);
        $this->assertSame('офталмологија', $items[0]->details['komora_specialty']);

        LicenceSpecialtyMapping::query()->where('source', 'komora')->where('source_key', SpecialtyKey::for('офталмологија'))
            ->update(['compatible_groups' => json_encode(['pedijatrija'])]);
        $this->adjudicate();

        $this->assertTrue($first->fresh()->isVerified());
        $this->assertTrue($second->fresh()->isVerified());
        $this->assertFalse($resident->fresh()->isVerified());
        $this->assertSame([], $this->uncertain('specialty_mapping'));
    }

    public function test_namesakes_on_the_list_who_all_hold_a_valid_fitting_licence_verify_the_profile_and_settle_the_licence_item(): void
    {
        $doctor = $this->fzomDoctor('Урош Истоимен', $this->facility());
        $this->licence('Урош Истоимен', 'педијатрија', outcome: 'ambiguous', candidates: [$doctor->id]);
        $this->licence('Урош Истоимен', 'болничка педијатрија', outcome: 'ambiguous', candidates: [$doctor->id]);

        // One of the two expired: no longer certain.
        $expired = $this->fzomDoctor('Филип Истоимен', $this->facility('ЈЗУ Трета', 'Охрид', '4030000000003'));
        $this->licence('Филип Истоимен', 'педијатрија', outcome: 'ambiguous', candidates: [$expired->id]);
        $this->licence('Филип Истоимен', 'педијатрија', outcome: 'ambiguous', candidates: [$expired->id], validUntil: '2026-01-01');

        $run = $this->adjudicate();

        $this->assertSame(VerificationBasis::OfficialRegisters, $doctor->fresh()->verification_basis);
        $this->assertSame(2, $doctor->fresh()->verification_reasons['evidence'][0]['namesake_licences']);
        $this->assertNull($doctor->fresh()->licence_number, 'No number is attached on a guess.');
        $this->assertFalse($expired->fresh()->isVerified());
        $this->assertSame('ambiguous_name', $this->reasons($expired)['reason']);

        // The re-match queued both rows of each name as ambiguous; those of
        // the verified profile are settled (and stay so on the next run).
        $this->assertSame(2, $run->counts['licence_items_settled_by_namesakes']);
        $open = fn () => ImportReviewItem::query()->open()->where('source', 'komora')->get()
            ->map(fn (ImportReviewItem $item): array => $item->details['candidate_doctor_ids'])->unique()->values()->all();
        $this->assertSame([[$expired->id]], $open());
        $this->assertSame(2, ImportReviewItem::query()->where('source', 'komora')->where('resolution', 'verified_without_number')->count());

        $this->adjudicate();
        $this->assertSame([[$expired->id]], $open());
    }

    public function test_staff_decisions_and_owner_claims(): void
    {
        $facility = $this->facility();
        $doctor = $this->fzomDoctor('Христина Рачно', $facility);
        $this->licence('Христина Рачно', 'педијатрија', $doctor);
        $staff = User::factory()->create();
        app(VerificationWriter::class)->unverify($doctor, 'Called the clinic: left in 2025', $staff);
        $owner = Doctor::factory()->create(['full_name' => 'Цветан Сопственик']);
        $owner->forceFill(['owner_user_id' => User::factory()->create()->id, 'owner_linked_at' => now(), 'owner_linked_by_id' => $staff->id])->saveQuietly();

        $run = $this->adjudicate();

        $this->assertFalse($doctor->fresh()->isVerified(), 'A staff decision is never overridden.');
        $this->assertSame(1, $run->counts['doctors_result.staff_decision_kept']);
        $this->assertSame(VerificationBasis::OwnerClaim, $owner->fresh()->verification_basis);
    }

    public function test_facilities_are_verified_from_the_register_and_pharmacies_wait(): void
    {
        $registered = $this->facility();
        $renamed = $this->facility('ЈЗУ Преименувана', 'Битола', '4030000000002');
        $renamed->forceFill(['name' => 'Сосема Друго Име', 'is_published' => true])->saveQuietly();
        $gone = $this->facility('ЈЗУ Исчезната', 'Охрид', '4030000000003');
        DB::table('facilities')->where('id', $gone->id)->update(['import_missing_runs' => 2]);
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);
        $websiteOnly = Facility::factory()->unpublished()->create();
        $this->website($websiteOnly);

        $run = $this->adjudicate();

        $this->assertSame(VerificationBasis::OfficialRegisters, $registered->fresh()->verification_basis);
        $this->assertSame('register_mismatch', $this->reasons($renamed)['reason']);
        $this->assertSame('source_removed', $this->reasons($gone)['reason']);
        $this->assertSame('no_pharmacy_register', $this->reasons($pharmacy)['reason']);
        $this->assertSame('not_in_register', $this->reasons($websiteOnly)['reason']);
        $this->assertSame(1, $run->counts['pharmacies_unverified.no_pharmacy_register']);
        // The published one that no longer matches is for staff.
        $this->assertCount(1, $this->uncertain('register_mismatch'));
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $doctor = $this->fzomDoctor('Чедо Суво', $this->facility());
        $this->licence('Чедо Суво', 'педијатрија', $doctor);
        $this->licence('Џема Безпрофилна', 'педијатрија');

        $run = $this->adjudicate(dryRun: true);

        $this->assertTrue($run->dry_run);
        $this->assertSame(1, $run->counts['doctors_verified.fzom_licence']);
        $this->assertSame(1, $run->counts['doctors_result.verified']);
        $this->assertNull($doctor->fresh()->verification_checked_at);
        $this->assertSame(0, ImportReviewItem::query()->count());
    }

    public function test_drafts_verified_in_a_run_are_published_only_when_auto_publish_is_on(): void
    {
        $facility = $this->facility();
        $first = $this->fzomDoctor('Шемси Автоматски', $facility);
        $this->licence('Шемси Автоматски', 'педијатрија', $first);
        foreach ([$first, $facility] as $subject) {
            ImportReviewItem::raise('fzom', ImportReviewKind::New, $subject instanceof Doctor ? 'doctor:'.$subject->id : 'facility:'.$subject->id, 'draft', [], $subject);
        }

        $this->adjudicate();
        $this->assertFalse($first->fresh()->is_published);
        $this->assertFalse($facility->fresh()->is_published);

        config(['import.verification.auto_publish' => true]);
        $second = $this->fzomDoctor('Ана Автоматска', $facility);
        $this->licence('Ана Автоматска', 'педијатрија', $second);
        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$second->id, 'draft', [], $second);
        $run = $this->adjudicate();

        // Only what this run newly verified: the backlog stays for staff.
        $this->assertTrue($second->fresh()->is_published);
        $this->assertFalse($first->fresh()->is_published);
        $this->assertFalse($facility->fresh()->is_published);
        $this->assertSame(1, $run->counts['auto_published']);
    }

    public function test_fzom_drafts_without_a_licence_are_auto_published_unverified_only_with_their_own_flag(): void
    {
        $facility = $this->facility();
        $backlog = $this->fzomDoctor('Анита Заостаната', $facility);
        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$backlog->id, 'draft', [], $backlog);

        config(['import.verification.auto_publish' => true]);
        $this->adjudicate();
        $this->assertFalse($backlog->fresh()->is_published, 'IMPORT_AUTO_PUBLISH_VERIFIED alone publishes verified drafts only.');
        $this->assertSame('fzom_no_licence', $this->reasons($backlog)['reason']);

        config(['import.verification.auto_publish_fzom_unverified' => true]);
        $fresh = $this->fzomDoctor('Бојан Новодојден', $facility);
        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$fresh->id, 'draft', [], $fresh);
        $duplicate = $this->fzomDoctor('Весна Двојничка', $facility);
        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$duplicate->id, 'draft', [], $duplicate);
        ImportReviewItem::raise('fzom', ImportReviewKind::Unmatched, 'possible-duplicate:'.$duplicate->id, 'possible duplicate', ['reason' => 'possible_duplicate'], $duplicate);
        // Two ФЗОМ doctors of one name, one licence on the list: either could hold it.
        $ambiguous = $this->fzomDoctor('Горан Двосмислен', $facility);
        $twin = $this->fzomDoctor('Горан Двосмислен', $this->facility('ЈЗУ Друга', 'Битола', '4030000000002'));
        foreach ([$ambiguous, $twin] as $doctor) {
            ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->id, 'draft', [], $doctor);
        }
        $this->licence('Горан Двосмислен', 'педијатрија');

        $run = $this->adjudicate();

        // Newly in the set this run: published, and still unverified.
        $this->assertTrue($fresh->fresh()->is_published);
        $this->assertFalse($fresh->fresh()->isVerified());
        $this->assertSame(1, $run->counts['auto_published_fzom_unverified']);
        // The backlog waits for the bulk action; a blocking item or an ambiguous licence keeps a draft hidden.
        $this->assertFalse($backlog->fresh()->is_published);
        $this->assertFalse($duplicate->fresh()->is_published);
        $this->assertFalse($ambiguous->fresh()->is_published || $twin->fresh()->is_published);
        $this->assertSame('ambiguous_name', $this->reasons($ambiguous)['reason']);
    }
}
