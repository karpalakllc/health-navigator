<?php

namespace Tests\Feature\Verification;

use App\Enums\FacilityType;
use App\Models\Activity;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerificationSource;
use App\Support\Verification\VerificationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The contract the import engine (W7-A) and the Filament actions write
 * through: status, basis, internal reasons, sticky staff decisions, audit.
 */
class VerificationWriterTest extends TestCase
{
    use RefreshDatabase;

    private function writer(): VerificationWriter
    {
        return app(VerificationWriter::class);
    }

    /**
     * @return list<string>
     */
    private function events(object $subject): array
    {
        return Activity::query()
            ->where('log_name', 'verification')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->orderBy('id')
            ->pluck('event')
            ->all();
    }

    public function test_automatic_verify_stores_basis_and_internal_evidence(): void
    {
        $doctor = Doctor::factory()->create();

        $result = $this->writer()->verify($doctor, VerificationBasis::OfficialRegisters, [['source' => 'fzom', 'facility_id' => 4]]);

        $doctor->refresh();
        $this->assertSame(VerificationResult::Verified, $result);
        $this->assertTrue($doctor->isVerified());
        $this->assertSame(VerificationBasis::OfficialRegisters, $doctor->verification_basis);
        $this->assertSame(VerificationSource::Auto, $doctor->verification_source);
        $this->assertNull($doctor->verified_by_id);
        $this->assertSame([['source' => 'fzom', 'facility_id' => 4]], $doctor->verification_reasons['evidence']);
        $this->assertNotNull($doctor->verification_checked_at);
        $this->assertSame(['verified'], $this->events($doctor));
    }

    public function test_reconfirming_the_same_basis_is_quiet(): void
    {
        $doctor = Doctor::factory()->create();
        $this->writer()->verify($doctor, VerificationBasis::OfficialRegisters);
        $doctor->refresh();
        $verifiedAt = $doctor->verified_at;
        $updatedAt = $doctor->updated_at;

        Carbon::setTestNow(now()->addDay());
        $result = $this->writer()->verify($doctor, VerificationBasis::OfficialRegisters, ['re-run']);
        $doctor->refresh();

        $this->assertSame(VerificationResult::Unchanged, $result);
        $this->assertTrue($verifiedAt->equalTo($doctor->verified_at));
        $this->assertTrue($updatedAt->equalTo($doctor->updated_at));
        $this->assertTrue($doctor->verification_checked_at->isToday());
        $this->assertSame(['re-run'], $doctor->verification_reasons['evidence']);
        $this->assertSame(['verified'], $this->events($doctor));
    }

    public function test_unverify_clears_status_and_keeps_the_reason(): void
    {
        $doctor = Doctor::factory()->create();
        $this->writer()->verify($doctor, VerificationBasis::LicenceAndWebsite);

        $result = $this->writer()->unverify($doctor, 'licence_expired');
        $doctor->refresh();

        $this->assertSame(VerificationResult::Unverified, $result);
        $this->assertFalse($doctor->isVerified());
        $this->assertNull($doctor->verification_basis);
        $this->assertSame('licence_expired', $doctor->verification_reasons['reason']);
        $this->assertSame('licence_and_website', $doctor->verification_reasons['previous_basis']);
        $this->assertSame(['verified', 'unverified'], $this->events($doctor));

        // Already unverified: reasons refresh, nothing logged.
        $this->assertSame(VerificationResult::Unchanged, $this->writer()->unverify($doctor, 'no_licence'));
        $this->assertSame('no_licence', $doctor->fresh()->verification_reasons['reason']);
        $this->assertSame(['verified', 'unverified'], $this->events($doctor));
    }

    public function test_a_staff_decision_is_kept_by_automatic_runs_until_released(): void
    {
        $staff = User::factory()->admin()->create();
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);

        $this->writer()->unverify($pharmacy, 'Closed since spring', $staff);
        $this->assertSame(VerificationResult::StaffDecisionKept, $this->writer()->verify($pharmacy, VerificationBasis::OfficialRegisters));
        $this->assertFalse($pharmacy->fresh()->isVerified());
        $this->assertSame($staff->id, $pharmacy->fresh()->verified_by_id);

        $this->writer()->release($pharmacy, $staff);
        $this->assertSame(VerificationResult::Verified, $this->writer()->verify($pharmacy, VerificationBasis::OfficialRegisters));
        $this->assertTrue($pharmacy->fresh()->isVerified());

        $log = Activity::query()->where('log_name', 'verification')->where('event', 'unverified')->sole();
        $this->assertSame($staff->id, $log->causer_id);
        $this->assertSame('Closed since spring', $log->properties['reason']);
        $this->assertSame(['unverified', 'released', 'verified'], $this->events($pharmacy));
    }

    public function test_the_doctor_profile_log_never_holds_the_evidence(): void
    {
        $doctor = Doctor::factory()->create();

        $this->writer()->verify($doctor, VerificationBasis::OfficialRegisters, [['licence_row' => 'secret-ish']]);

        $this->assertFalse(Activity::query()->where('log_name', 'doctor_profile')->where('properties', 'like', '%secret-ish%')->exists());
        $this->assertFalse(Activity::query()->where('log_name', 'verification')->where('properties', 'like', '%secret-ish%')->exists());
    }

    public function test_public_verification_exposes_status_and_label_only(): void
    {
        app()->setLocale('mk');
        $doctor = Doctor::factory()->create();
        $facility = Facility::factory()->create();
        $this->assertSame(['status' => 'unverified', 'basis' => null, 'basis_label' => null], $doctor->publicVerification());

        $this->writer()->verify($doctor, VerificationBasis::OfficialRegisters, ['x']);
        $this->writer()->verify($facility, VerificationBasis::OfficialRegisters, ['x']);

        $this->assertSame([
            'status' => 'verified',
            'basis' => 'official_registers',
            'basis_label' => 'Регистар на ФЗОМ и Лекарска комора',
        ], $doctor->fresh()->publicVerification());
        $this->assertSame('Регистар на ФЗОМ', $facility->fresh()->publicVerification()['basis_label']);
        $this->assertSame(1, Doctor::query()->verified()->count());
    }

    public function test_an_automatic_write_from_a_stale_copy_never_overrides_a_concurrent_staff_decision(): void
    {
        $doctor = Doctor::factory()->create();
        $this->writer()->unverify($doctor, 'fzom_no_licence'); // an earlier engine run
        $stale = Doctor::query()->find($doctor->id); // the engine's chunk copy
        $staff = User::factory()->create();
        $this->writer()->unverify(Doctor::query()->find($doctor->id), 'Called: left in 2025', $staff);

        $this->assertSame(VerificationResult::StaffDecisionKept, $this->writer()->verify($stale, VerificationBasis::OfficialRegisters, [['rule' => 'fzom_licence']]));
        $this->assertSame(VerificationResult::StaffDecisionKept, $this->writer()->unverify($stale, 'source_removed'));

        $fresh = $doctor->fresh();
        $this->assertFalse($fresh->isVerified());
        $this->assertSame(VerificationSource::Staff, $fresh->verification_source);
        $this->assertSame('Called: left in 2025', $fresh->verification_reasons['reason']);
        $this->assertSame($staff->id, $fresh->verified_by_id);
        // The stale copy now shows the staff decision too.
        $this->assertTrue($stale->hasStaffVerificationDecision());
    }
}
