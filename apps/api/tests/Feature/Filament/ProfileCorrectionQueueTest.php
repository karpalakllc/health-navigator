<?php

namespace Tests\Feature\Filament;

use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\UserKind;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\ProfileCorrections\Pages\ListProfileCorrections;
use App\Filament\Resources\ProfileCorrections\Pages\ViewProfileCorrection;
use App\Filament\Resources\ProfileCorrections\ProfileCorrectionResource;
use App\Models\Activity;
use App\Models\Doctor;
use App\Models\ProfileCorrection;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfileCorrectionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staffWithRole(string $roleName): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([$roleName]);

        return $user;
    }

    private function request(ProfileCorrectionType $type = ProfileCorrectionType::Correction, array $attributes = []): ProfileCorrection
    {
        return ProfileCorrection::query()->create([
            'type' => $type,
            'subject_type' => Doctor::class,
            'subject_id' => Doctor::factory()->create()->id,
            'field' => $type === ProfileCorrectionType::Correction ? ProfileCorrectionField::OfficeHours : null,
            'message' => 'Работното време е застарено.',
            'contact' => 'pacient@example.test',
            'due_at' => now()->addDays($type->dueDays()),
            ...$attributes,
        ]);
    }

    public function test_only_the_administrator_holds_the_queue_permissions_by_default(): void
    {
        foreach (PermissionCatalog::profileCorrections() as $permission) {
            $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR)->hasPermissionTo($permission), $permission);

            foreach ([RoleCatalog::MODERATOR, RoleCatalog::FORUM_MODERATOR, RoleCatalog::MEMBER] as $role) {
                $this->assertFalse(Role::findByName($role)->hasPermissionTo($permission), $role.' '.$permission);
            }
        }
    }

    public function test_the_permission_migration_grants_existing_roles_additively(): void
    {
        Permission::query()->whereIn('name', PermissionCatalog::profileCorrections())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migration = require database_path('migrations/2026_10_15_120001_grant_profile_correction_permissions.php');
        $migration->up();
        $migration->up(); // re-runnable

        $admin = Role::findByName(RoleCatalog::ADMINISTRATOR)->fresh();
        foreach (PermissionCatalog::profileCorrections() as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission), $permission);
        }
        $this->assertFalse(Role::findByName(RoleCatalog::MODERATOR)->hasPermissionTo('profile_corrections.view'));
    }

    public function test_a_moderator_cannot_open_the_queue(): void
    {
        $this->actingAs($this->staffWithRole(RoleCatalog::MODERATOR));

        $this->assertFalse(ProfileCorrectionResource::canViewAny());
        $this->assertFalse(ProfileCorrectionResource::canCreate());
    }

    public function test_the_administrator_sees_open_requests_soonest_due_first_and_marks_one_corrected(): void
    {
        $later = $this->request(ProfileCorrectionType::Objection);
        $sooner = $this->request();
        $closed = $this->request(attributes: ['status' => ProfileCorrectionStatus::Declined, 'resolved_at' => now()]);
        $admin = $this->staffWithRole(RoleCatalog::ADMINISTRATOR);
        $this->actingAs($admin);

        Livewire::test(ListProfileCorrections::class)
            ->assertCanSeeTableRecords([$sooner, $later], inOrder: true)
            ->assertCanNotSeeTableRecords([$closed])
            ->callTableAction('resolve', $sooner, ['resolution_note' => 'Работното време е исправено.']);

        $sooner->refresh();
        $this->assertSame(ProfileCorrectionStatus::Resolved, $sooner->status);
        $this->assertSame($admin->id, $sooner->resolved_by_id);
        $this->assertNotNull($sooner->resolved_at);
        $this->assertSame('Работното време е исправено.', $sooner->resolution_note);

        $entry = Activity::query()->where('log_name', 'profile_corrections')->sole();
        $this->assertSame('resolved', $entry->event);
        $this->assertSame($admin->id, $entry->causer_id);
        $this->assertStringNotContainsString('pacient@example.test', json_encode($entry->properties, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('исправено', json_encode($entry->properties, JSON_THROW_ON_ERROR));
    }

    public function test_an_objection_is_refused_from_the_view_page_with_reasons_required(): void
    {
        $objection = $this->request(ProfileCorrectionType::Objection);
        $this->actingAs($this->staffWithRole(RoleCatalog::ADMINISTRATOR));

        Livewire::test(ViewProfileCorrection::class, ['record' => $objection->getRouteKey()])
            ->assertActionVisible('editProfile')
            ->callAction('decline', ['resolution_note' => ''])
            ->assertHasActionErrors(['resolution_note' => 'required']);

        $this->assertTrue($objection->fresh()->isOpen());

        Livewire::test(ViewProfileCorrection::class, ['record' => $objection->getRouteKey()])
            ->callAction('decline', ['resolution_note' => 'Јавниот интерес за целосен именик преовладува; одговорено на е-адреса.']);

        $this->assertSame(ProfileCorrectionStatus::Declined, $objection->fresh()->status);
    }

    public function test_the_edit_link_points_at_the_profile_and_needs_the_profile_permission(): void
    {
        $request = $this->request();
        $admin = $this->staffWithRole(RoleCatalog::ADMINISTRATOR);
        $this->actingAs($admin);

        $this->assertSame(
            DoctorResource::getUrl('edit', ['record' => $request->subject]),
            ProfileCorrectionResource::profileEditUrl($request),
        );

        $limited = User::factory()->create(['user_kind' => UserKind::Staff]);
        $limited->givePermissionTo(['admin.access', 'profile_corrections.view', 'profile_corrections.resolve']);
        $this->actingAs($limited);

        $this->assertNull(ProfileCorrectionResource::profileEditUrl($request->fresh()));

        $request->subject->delete();
        $this->actingAs($admin);
        $this->assertNull(ProfileCorrectionResource::profileEditUrl($request->fresh()));
        $this->assertSame($request->subject->full_name, $request->fresh()->subjectName());
    }

    public function test_a_second_close_does_not_overwrite_the_first_decision(): void
    {
        $request = $this->request();
        $first = $this->staffWithRole(RoleCatalog::ADMINISTRATOR);
        $second = $this->staffWithRole(RoleCatalog::ADMINISTRATOR);

        $this->assertTrue($request->close(ProfileCorrectionStatus::Resolved, $first, 'Исправено.'));
        $this->assertFalse($request->close(ProfileCorrectionStatus::Declined, $second, 'Без промена.'));

        $request->refresh();
        $this->assertSame(ProfileCorrectionStatus::Resolved, $request->status);
        $this->assertSame($first->id, $request->resolved_by_id);
    }

    public function test_the_navigation_badge_counts_open_requests_and_turns_red_when_one_is_overdue(): void
    {
        $this->request();
        $this->assertSame('1', ProfileCorrectionResource::getNavigationBadge());
        $this->assertSame('warning', ProfileCorrectionResource::getNavigationBadgeColor());

        $this->request(attributes: ['due_at' => now()->subDay()]);
        $this->assertSame('2', ProfileCorrectionResource::getNavigationBadge());
        $this->assertSame('danger', ProfileCorrectionResource::getNavigationBadgeColor());
    }
}
