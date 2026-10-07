<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use App\Models\ProfileCorrection;
use App\Models\User;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceAttachResult;
use App\Support\Import\Contracts\LicenceRecord;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\Import\ImportReviewActions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A doctor removed after an upheld objection, or deleted by staff, stays
 * removed: no import re-creates or re-publishes them until staff lift the
 * suppression.
 */
class ImportSuppressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local', 'import.max_missing_ratio' => 0.9]);
    }

    private function import(): ImportRun
    {
        $this->travel(1)->minutes();

        return app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    private function objection(Doctor $doctor): ProfileCorrection
    {
        return ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Objection,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->getKey(),
            'message' => 'Не сакам да бидам во именикот.',
            'contact' => 'lekar@example.test',
            'due_at' => now()->addDays(30),
        ]);
    }

    public function test_a_force_deleted_doctor_is_not_created_again_by_the_next_run(): void
    {
        $this->import();
        Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail()->forceDelete();

        $run = $this->import();

        $this->assertSame(0, Doctor::withTrashed()->where('fzo_facsimile', '900010')->count());
        $this->assertSame(0, Doctor::withTrashed()->where('name_key', 'ЧЕТВРТИ СРЦЕВСКИ')->count());
        $this->assertSame(1, $run->count('doctors_suppressed'));
        $this->assertSame(0, ImportReviewItem::query()->where('import_run_id', $run->getKey())->where('title', 'like', '%Срцевски%')->count());
    }

    public function test_a_deleted_staff_profile_without_a_facsimile_is_not_created_again_by_name(): void
    {
        $doctor = Doctor::factory()->create(['full_name' => 'Шести Ретковски', 'city' => 'Тестово', 'is_published' => true]);
        $doctor->delete();

        $this->import();

        $this->assertSame(0, Doctor::query()->where('fzo_facsimile', '900012')->count());
        $this->assertSame(1, Doctor::query()->where('fzo_facsimile', '900010')->count(), 'Other doctors are imported as usual.');
    }

    public function test_an_upheld_objection_unpublishes_and_nothing_publishes_the_profile_again(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $doctor->forceFill(['is_published' => true, 'published_at' => now()])->save();
        $staff = User::factory()->create();

        $this->assertTrue($this->objection($doctor)->close(ProfileCorrectionStatus::Resolved, $staff, 'Лекарот веќе не работи.'));

        $this->assertFalse($doctor->refresh()->is_published, 'Upholding unpublishes.');
        $suppression = ImportSuppression::query()->active()->where('doctor_id', $doctor->getKey())->firstOrFail();
        $this->assertSame(ImportSuppression::REASON_OBJECTION, $suppression->reason);

        // Publishing closed the import's "new" item; one left open (raised
        // again, e.g. by a later run) still cannot publish it.
        $this->assertSame(0, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->where('subject_type', 'doctor')->where('subject_id', $doctor->getKey())->count());
        $item = ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->getKey(), 'draft', [], $doctor);
        $this->assertFalse(app(ImportReviewActions::class)->publish($item, $staff), 'Publish refuses.');
        $this->assertFalse($doctor->refresh()->is_published);

        // Deleted for good later on: still not created again.
        $doctor->forceDelete();
        $this->import();
        $this->assertSame(0, Doctor::withTrashed()->where('fzo_facsimile', '900010')->count());
    }

    public function test_staff_can_lift_a_suppression(): void
    {
        $this->import();
        Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail()->forceDelete();
        ImportSuppression::query()->active()->firstOrFail()->lift(User::factory()->create());

        $this->import();

        $this->assertSame(1, Doctor::query()->where('fzo_facsimile', '900010')->count());
    }

    public function test_restoring_a_deleted_doctor_lifts_its_deletion_suppression_only(): void
    {
        $doctor = Doctor::factory()->create();
        $doctor->delete();
        $this->assertSame(1, ImportSuppression::query()->active()->count());

        $doctor->restore();
        $this->assertSame(0, ImportSuppression::query()->active()->count());
    }

    public function test_the_licence_import_leaves_a_suppressed_doctor_alone(): void
    {
        $doctor = Doctor::factory()->create(['licence_number' => null]);
        $this->objection($doctor)->close(ProfileCorrectionStatus::Resolved, User::factory()->create(), 'Отстранет на барање.');

        $result = app(DoctorLicenceSink::class)->attach($doctor->getKey(), new LicenceRecord(
            licenceNumber: '0099001',
            validUntil: CarbonImmutable::parse('2031-01-01'),
            fullName: 'ИЗМИСЛЕНА ЛИЧНОСТ',
            specialty: 'радиологија',
            observedAt: CarbonImmutable::parse('2026-07-02'),
        ));

        $this->assertSame(LicenceAttachResult::Locked, $result);
        $this->assertNull($doctor->refresh()->licence_number);
    }
}
