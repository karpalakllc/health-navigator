<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Staff\Pages\CreateStaffUser;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $admin = User::factory()->create(['role' => UserRole::Admin, 'user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    public function test_a_staff_account_created_in_the_panel_is_verified(): void
    {
        $this->actingAs($this->administrator());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'New Editor',
                'email' => 'editor@example.com',
                'role' => UserRole::Moderator->value,
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(User::query()->where('email', 'editor@example.com')->sole()->email_verified_at);
    }

    public function test_creating_an_address_that_differs_only_in_case_is_a_form_error(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $this->actingAs($this->administrator());

        Livewire::test(CreateStaffUser::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => 'DUP@example.com',
                'role' => UserRole::Moderator->value,
                'password' => 'long1enough1password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::query()->where('email', 'dup@example.com')->count());
    }

    public function test_editing_to_an_address_that_differs_only_in_case_is_a_form_error(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com', 'role' => UserRole::Moderator, 'user_kind' => UserKind::Staff]);
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
            'role' => UserRole::Admin,
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
