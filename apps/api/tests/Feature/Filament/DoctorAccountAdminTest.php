<?php

namespace Tests\Feature\Filament;

use App\Enums\DoctorChangeRequestStatus;
use App\Enums\DoctorClaimRequestStatus;
use App\Enums\ReviewResponseStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ActivityLog\ActivityResource;
use App\Filament\Resources\ActivityLog\Pages\ListActivities;
use App\Filament\Resources\DoctorChangeRequests\Pages\ListDoctorChangeRequests;
use App\Filament\Resources\DoctorChangeRequests\Pages\ViewDoctorChangeRequest;
use App\Filament\Resources\DoctorClaimRequests\Pages\ListDoctorClaimRequests;
use App\Filament\Resources\DoctorReplies\Pages\ListDoctorReplies;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Support\DoctorOwnerActions;
use App\Mail\DoctorChangeRequestDecidedMail;
use App\Models\Activity;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\DoctorClaimRequest;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff side of doctor accounts: assigning the account, the change-request,
 * claim and doctor-reply queues, and who may use them.
 */
class DoctorAccountAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Mail::fake();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_only_the_administrator_holds_the_new_permissions_by_default(): void
    {
        foreach (PermissionCatalog::doctorAccountsAndAudit() as $permission) {
            $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->hasPermissionTo($permission), $permission);

            foreach ([RoleCatalog::MODERATOR, RoleCatalog::FORUM_MODERATOR, RoleCatalog::MEMBER] as $role) {
                $this->assertFalse(Role::findByName($role)->hasPermissionTo($permission), "{$role}: {$permission}");
            }
        }
    }

    public function test_the_permission_migration_grants_existing_administrators_additively(): void
    {
        Permission::query()->whereIn('name', PermissionCatalog::doctorAccountsAndAudit())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migration = require database_path('migrations/2026_10_14_110006_grant_doctor_owner_and_audit_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->fresh()->hasPermissionTo('doctors.assign_owner'));
        $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->fresh()->hasPermissionTo('audit.view'));
        $this->assertFalse(Role::findByName(RoleCatalog::MODERATOR)->fresh()->hasPermissionTo('audit.view'));
    }

    public function test_an_administrator_assigns_and_removes_the_account_from_the_doctor_page(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create(['email' => 'ana.doctor@example.com']);
        $this->actingAs($admin);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->assertActionVisible('assignOwner')
            ->assertActionHidden('removeOwner')
            ->callAction('assignOwner', ['user_id' => $member->id])
            ->assertHasNoActionErrors();

        $doctor->refresh();
        $this->assertSame($member->id, $doctor->owner_user_id);
        $this->assertSame($admin->id, $doctor->owner_linked_by_id);
        $this->assertNotNull($doctor->owner_linked_at);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->assertActionHidden('assignOwner')
            ->callAction('removeOwner');

        $this->assertNull($doctor->fresh()->owner_user_id);
    }

    public function test_one_account_cannot_manage_two_profiles(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $member = User::factory()->create();
        Doctor::factory()->create(['owner_user_id' => $member->id]);
        $second = Doctor::factory()->create();
        $this->actingAs($admin);

        Livewire::test(EditDoctor::class, ['record' => $second->getRouteKey()])
            ->callAction('assignOwner', ['user_id' => $member->id]);

        $this->assertNull($second->fresh()->owner_user_id);
    }

    public function test_the_account_search_offers_active_members_without_a_profile_only(): void
    {
        $free = User::factory()->create(['email' => 'free.doc@example.com']);
        $taken = User::factory()->create(['email' => 'taken.doc@example.com']);
        Doctor::factory()->create(['owner_user_id' => $taken->id]);
        User::factory()->create(['email' => 'staff.doc@example.com', 'user_kind' => UserKind::Staff]);
        User::factory()->create(['email' => 'gone.doc@example.com', 'suspended_at' => now()]);

        $this->assertSame([$free->id => 'free.doc@example.com'], DoctorOwnerActions::searchAccounts('.doc@'));
        $this->assertSame([], DoctorOwnerActions::searchAccounts('do'));
    }

    public function test_a_moderator_cannot_assign_accounts_or_open_the_claims_or_the_log(): void
    {
        $moderator = $this->staff(RoleCatalog::MODERATOR);
        $doctor = Doctor::factory()->create();
        $this->actingAs($moderator);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->assertForbidden();

        $this->get(ActivityResource::getUrl('index'))->assertForbidden();
        $this->get('/admin/doctor-claim-requests')->assertForbidden();
        $this->get('/admin/doctor-change-requests')->assertForbidden();
    }

    public function test_editing_doctors_does_not_include_assigning_their_account(): void
    {
        Role::findOrCreate('Directory Editor', 'web')->syncPermissions(['admin.access', 'doctors.view', 'doctors.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $editor = $this->staff('Directory Editor');
        $doctor = Doctor::factory()->create();
        $this->actingAs($editor);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->assertActionHidden('assignOwner')
            ->assertActionHidden('removeOwner');
    }

    public function test_staff_approve_a_change_request_from_the_queue(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $doctor = Doctor::factory()->create(['full_name' => 'д-р Стара']);
        $account = User::factory()->create();
        $request = DoctorChangeRequest::query()->create([
            'doctor_id' => $doctor->id,
            'user_id' => $account->id,
            'changes' => ['full_name' => ['old' => 'д-р Стара', 'new' => 'д-р Нова']],
        ]);
        $this->actingAs($admin);

        Livewire::test(ListDoctorChangeRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request);

        $this->assertSame(DoctorChangeRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->reviewed_by_id);
        $this->assertSame('д-р Нова', $doctor->fresh()->full_name);
        Mail::assertQueued(DoctorChangeRequestDecidedMail::class);
    }

    public function test_rejecting_a_change_request_requires_a_reason(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $doctor = Doctor::factory()->create(['full_name' => 'д-р Стара']);
        $request = DoctorChangeRequest::query()->create([
            'doctor_id' => $doctor->id,
            'user_id' => User::factory()->create()->id,
            'changes' => ['full_name' => ['old' => 'д-р Стара', 'new' => 'д-р Нова']],
        ]);
        $this->actingAs($admin);

        Livewire::test(ViewDoctorChangeRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('reject', ['rejection_reason' => ''])
            ->assertHasActionErrors(['rejection_reason' => 'required']);
        $this->assertTrue($request->fresh()->isPending());

        Livewire::test(ViewDoctorChangeRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('reject', ['rejection_reason' => 'Не е потврдено.']);

        $this->assertSame(DoctorChangeRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame('д-р Стара', $doctor->fresh()->full_name);
    }

    public function test_a_claim_is_assigned_from_the_queue(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        $claim = DoctorClaimRequest::query()->create([
            'doctor_id' => $doctor->id,
            'user_id' => $member->id,
            'message' => 'Мој профил е.',
            'contact' => '070 000 000',
        ]);
        $this->actingAs($admin);

        Livewire::test(ListDoctorClaimRequests::class)
            ->assertCanSeeTableRecords([$claim])
            ->callTableAction('assign', $claim);

        $this->assertSame(DoctorClaimRequestStatus::Approved, $claim->fresh()->status);
        $this->assertSame($member->id, $doctor->fresh()->owner_user_id);
    }

    public function test_doctor_replies_are_approved_or_rejected_by_staff(): void
    {
        $moderator = $this->staff(RoleCatalog::MODERATOR);
        $doctor = Doctor::factory()->create();
        $account = User::factory()->create();
        $first = Review::factory()->approved()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $second = Review::factory()->approved()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $first->replyAsDoctor($account, 'Благодарам.', true);
        $second->replyAsDoctor($account, 'Се сеќавам на вас од прегледот.', true);
        $this->actingAs($moderator);

        Livewire::test(ListDoctorReplies::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->callTableAction('approveDoctorReply', $first);

        Livewire::test(ViewReview::class, ['record' => $second->getRouteKey()])
            ->assertActionHidden('respond')
            ->callAction('rejectDoctorReply', ['response_rejection_note' => 'Потврдува дека авторот е пациент.']);

        $this->assertSame(ReviewResponseStatus::Approved, $first->fresh()->response_status);
        $this->assertSame($moderator->id, $first->fresh()->response_moderated_by_id);
        $this->assertSame(ReviewResponseStatus::Rejected, $second->fresh()->response_status);
        $this->assertSame('Потврдува дека авторот е пациент.', $second->fresh()->response_rejection_note);
    }

    public function test_the_administrator_reads_the_activity_log(): void
    {
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $this->actingAs($admin);
        $doctor = Doctor::factory()->create(['is_featured' => false]);
        $doctor->update(['is_featured' => true]);

        $this->get(ActivityResource::getUrl('index'))->assertOk();

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords(
                Activity::query()->where('subject_type', Doctor::class)->get(),
            );
    }
}
