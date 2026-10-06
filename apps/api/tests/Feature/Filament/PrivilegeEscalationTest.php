<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Staff\Pages\CreateStaffUser;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Filament\Resources\Staff\StaffUserResource;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A staff member trusted with user or role management must not be able to
 * grant more than they hold: no Administrator role, no
 * taking over an administrator account, no widening their own role.
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private const MANAGER_PERMISSIONS = [
        'admin.access',
        'staff.view', 'staff.create', 'staff.update', 'staff.delete',
        'roles.view', 'roles.create', 'roles.update', 'roles.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findOrCreate('User Manager', 'web')->syncPermissions(self::MANAGER_PERMISSIONS);
        Role::findOrCreate('Staff Viewer', 'web')->syncPermissions(['admin.access', 'staff.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function staff(?string $roleName): User
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            // Enrolled in two-factor, so page requests reach authorisation
            // instead of stopping at the panel's MFA set-up redirect.
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        if ($roleName !== null) {
            $user->syncRoles([$roleName]);
        }

        return $user;
    }

    private function roleId(string $name): int
    {
        return (int) Role::findByName($name, 'web')->getKey();
    }

    public function test_a_user_manager_cannot_grant_the_administrator_role(): void
    {
        $manager = $this->staff('User Manager');
        $target = $this->staff(null);
        $this->actingAs($manager);

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['roles' => [$this->roleId('Administrator')]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($target->fresh()->hasRole('Administrator'));
    }

    public function test_a_user_manager_cannot_make_themselves_administrator(): void
    {
        $manager = $this->staff('User Manager');
        $this->actingAs($manager);

        Livewire::test(EditStaffUser::class, ['record' => $manager->getKey()])
            ->fillForm(['roles' => [$this->roleId('User Manager'), $this->roleId('Administrator')]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($manager->fresh()->hasRole('Administrator'));
    }

    public function test_a_user_manager_cannot_grant_a_role_with_permissions_they_lack(): void
    {
        $manager = $this->staff('User Manager');
        $target = $this->staff(null);
        $this->actingAs($manager);

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['roles' => [$this->roleId('Moderator')]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($target->fresh()->hasRole('Moderator'));
    }

    public function test_a_user_manager_can_grant_a_role_within_their_own_permissions(): void
    {
        $manager = $this->staff('User Manager');
        $target = $this->staff(null);
        $this->actingAs($manager);

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['roles' => [$this->roleId('Staff Viewer')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('Staff Viewer'));
    }

    /**
     * The legacy `users.role` column used to be a second, separately guarded
     * way to make an administrator. The form no longer has it, and nothing a
     * manager submits under that name reaches the column.
     */
    public function test_the_staff_form_does_not_write_the_legacy_role_column(): void
    {
        $manager = $this->staff('User Manager');
        $target = $this->staff('Staff Viewer');
        $this->actingAs($manager);

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($target->fresh()->getRawOriginal('role'));
        $this->assertFalse($target->fresh()->isAdmin());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'Sneaky Admin',
                'email' => 'sneaky@example.test',
                'role' => 'admin',
                'roles' => [$this->roleId('Staff Viewer')],
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sneaky = User::query()->where('email', 'sneaky@example.test')->sole();
        $this->assertNull($sneaky->getRawOriginal('role'));
        $this->assertFalse($sneaky->isAdmin());
    }

    public function test_a_user_manager_cannot_edit_or_delete_an_administrator(): void
    {
        $manager = $this->staff('User Manager');
        $admin = $this->staff('Administrator');

        $this->actingAs($manager)
            ->get(StaffUserResource::getUrl('edit', ['record' => $admin]))
            ->assertForbidden();

        $this->assertFalse($manager->can('update', $admin));
        $this->assertFalse($manager->can('delete', $admin));
    }

    public function test_a_user_manager_cannot_edit_a_user_holding_permissions_they_lack(): void
    {
        $manager = $this->staff('User Manager');
        $moderator = $this->staff('Moderator');

        // Changing this account's password would hand the manager its
        // review and forum moderation rights.
        $this->assertFalse($manager->can('update', $moderator));
        $this->assertTrue($manager->can('update', $this->staff('Staff Viewer')));
    }

    public function test_a_role_manager_cannot_edit_their_own_role_or_administrator(): void
    {
        $manager = $this->staff('User Manager');
        $this->actingAs($manager);

        $this->get(RoleResource::getUrl('edit', ['record' => Role::findByName('User Manager', 'web')]))
            ->assertForbidden();
        $this->get(RoleResource::getUrl('edit', ['record' => Role::findByName('Administrator', 'web')]))
            ->assertForbidden();

        $this->assertFalse($manager->can('delete', Role::findByName('Administrator', 'web')));
    }

    public function test_a_role_manager_cannot_add_permissions_they_lack_to_a_role(): void
    {
        $manager = $this->staff('User Manager');
        $this->actingAs($manager);
        $role = Role::findByName('Staff Viewer', 'web');

        $settings = (int) Permission::findByName('settings.update', 'web')->getKey();
        $staffView = (int) Permission::findByName('staff.view', 'web')->getKey();

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm(['permissions' => [$staffView, $settings]])
            ->call('save')
            ->assertHasFormErrors(['permissions']);

        $this->assertFalse($role->fresh()->hasPermissionTo('settings.update'));
    }

    public function test_an_administrator_can_still_grant_the_administrator_role(): void
    {
        $admin = $this->staff('Administrator');
        $target = $this->staff(null);
        $this->actingAs($admin);

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['roles' => [$this->roleId('Administrator')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('Administrator'));
    }
}
