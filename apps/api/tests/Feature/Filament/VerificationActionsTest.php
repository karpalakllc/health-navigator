<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Models\Activity;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerificationSource;
use App\Support\Verification\VerificationWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff Verify / Unverify / „Return to automatic checks“ on doctor, facility
 * and pharmacy edit pages: a reason is required, the decision is audit
 * logged, and automatic import runs keep it.
 */
class VerificationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staff(string $role = RoleCatalog::ADMINISTRATOR): User
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_staff_verify_a_doctor_with_a_required_reason_and_it_is_logged(): void
    {
        $admin = $this->staff();
        $doctor = Doctor::factory()->create();
        $this->actingAs($admin);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->assertActionVisible('verifyProfile')
            ->assertActionHidden('releaseVerification')
            ->callAction('verifyProfile', ['basis' => 'staff', 'reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertFalse($doctor->fresh()->isVerified());

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->callAction('verifyProfile', ['basis' => 'staff', 'reason' => 'Called the clinic, confirmed.'])
            ->assertHasNoActionErrors();

        $doctor->refresh();
        $this->assertTrue($doctor->isVerified());
        $this->assertSame(VerificationBasis::Staff, $doctor->verification_basis);
        $this->assertSame(VerificationSource::Staff, $doctor->verification_source);
        $this->assertSame($admin->id, $doctor->verified_by_id);

        $log = Activity::query()->where('log_name', 'verification')->where('event', 'verified')->sole();
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame('Called the clinic, confirmed.', $log->properties['note']);
        $this->assertSame('staff', $log->properties['basis']);

        // An automatic run keeps the staff decision.
        $this->assertSame(VerificationResult::StaffDecisionKept, app(VerificationWriter::class)->unverify($doctor, 'no_licence'));
        $this->assertTrue($doctor->fresh()->isVerified());
    }

    public function test_staff_unverify_a_facility_and_return_it_to_automatic_checks(): void
    {
        $admin = $this->staff();
        $facility = Facility::factory()->create();
        app(VerificationWriter::class)->verify($facility, VerificationBasis::OfficialRegisters);
        $this->actingAs($admin);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->callAction('unverifyProfile', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->callAction('unverifyProfile', ['reason' => 'Moved to a new address, register not updated yet.'])
            ->assertHasNoActionErrors();

        $facility->refresh();
        $this->assertFalse($facility->isVerified());
        $this->assertTrue($facility->hasStaffVerificationDecision());
        $this->assertSame('Moved to a new address, register not updated yet.', $facility->verification_reasons['reason']);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->assertActionVisible('releaseVerification')
            ->callAction('releaseVerification');

        $this->assertFalse($facility->fresh()->hasStaffVerificationDecision());
        $this->assertSame(
            ['verified', 'unverified', 'released'],
            Activity::query()->where('log_name', 'verification')->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_pharmacy_edit_page_offers_the_actions(): void
    {
        $this->actingAs($this->staff());
        $pharmacy = Facility::factory()->pharmacy()->create();

        Livewire::test(EditPharmacy::class, ['record' => $pharmacy->getRouteKey()])
            ->assertActionVisible('verifyProfile')
            ->callAction('verifyProfile', ['basis' => 'official_registers', 'reason' => 'In the Ministry list.'])
            ->assertHasNoActionErrors();

        $this->assertTrue($pharmacy->fresh()->isVerified());
    }

    public function test_the_doctors_table_filters_by_verification(): void
    {
        $this->actingAs($this->staff());
        $verified = Doctor::factory()->create();
        $unverified = Doctor::factory()->create();
        app(VerificationWriter::class)->verify($verified, VerificationBasis::OfficialRegisters);

        Livewire::test(ListDoctors::class)
            ->assertCanSeeTableRecords([$verified, $unverified])
            ->filterTable('verified', true)
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$unverified])
            ->filterTable('verified', false)
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified]);
    }
}
