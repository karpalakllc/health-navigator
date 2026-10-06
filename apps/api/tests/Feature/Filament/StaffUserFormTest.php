<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Staff\Pages\CreateStaffUser;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff accounts as the admin panel creates them, and how the panel matches
 * their addresses.
 */
class StaffUserFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        Filament::setCurrentPanel('admin');
    }

    private function administrator(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_staff_account_created_in_the_panel_is_verified(): void
    {
        $this->actingAs($this->administrator());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'editor@example.com',
                'roles' => [$this->moderatorRoleId()],
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'editor@example.com')->sole();
        $this->assertNotNull($created->email_verified_at);
        // The chosen Spatie role is the account's authorization; nothing is
        // derived from (or written to) the legacy `role` column.
        $this->assertSame(['Moderator'], $created->getRoleNames()->all());
        $this->assertNull($created->getRawOriginal('role'));
    }

    public function test_a_staff_account_needs_at_least_one_role(): void
    {
        $this->actingAs($this->administrator());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'Roleless',
                'email' => 'roleless@example.com',
                'roles' => [],
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasFormErrors(['roles' => 'required']);

        $this->assertFalse(User::query()->where('email', 'roleless@example.com')->exists());
    }

    private function moderatorRoleId(): int
    {
        return (int) Role::findByName('Moderator', 'web')->getKey();
    }

    public function test_creating_an_address_that_differs_only_in_case_is_a_form_error(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $this->actingAs($this->administrator());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => 'DUP@example.com',
                'roles' => [$this->moderatorRoleId()],
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::query()->where('email', 'dup@example.com')->count());
    }

    public function test_editing_to_an_address_that_differs_only_in_case_is_a_form_error(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $target = User::factory()->moderator()->create(['email' => 'target@example.com']);
        $this->actingAs($this->administrator());

        Livewire::test(EditStaffUser::class, ['record' => $target->getKey()])
            ->fillForm(['email' => 'DUP@example.com'])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame('target@example.com', $target->fresh()->email);
    }

    public function test_the_admin_sign_in_matches_the_address_case_insensitively(): void
    {
        $admin = User::factory()->create([
            'email' => 'ops@example.com',
            'password' => 'long1enough1password',
            'user_kind' => UserKind::Staff,
        ]);
        $admin->syncRoles(['Administrator']);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'Ops@Example.com',
                'password' => 'long1enough1password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }
}
