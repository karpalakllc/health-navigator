<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Passwords set from the admin panel follow the same strength rule as
 * registration, and setting one ends the account's existing API sessions.
 */
class AdminSetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);
    }

    private function staffMember(): User
    {
        return User::factory()->create(['role' => UserRole::Moderator, 'user_kind' => UserKind::Staff]);
    }

    private function client(): User
    {
        return User::factory()->create(['role' => UserRole::Member, 'user_kind' => UserKind::Client]);
    }

    public function test_staff_form_rejects_a_weak_password(): void
    {
        $staff = $this->staffMember();

        Livewire::test(EditStaffUser::class, ['record' => $staff->getKey()])
            ->fillForm(['password' => 'short'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_client_form_rejects_a_weak_password(): void
    {
        $client = $this->client();

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->fillForm(['password' => 'short'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_setting_a_password_in_the_staff_form_revokes_api_tokens(): void
    {
        $staff = $this->staffMember();
        $staff->createToken('web');

        Livewire::test(EditStaffUser::class, ['record' => $staff->getKey()])
            ->fillForm(['password' => 'brand1new1password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('brand1new1password', $staff->fresh()->password));
        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_setting_a_password_in_the_client_form_revokes_api_tokens(): void
    {
        $client = $this->client();
        $client->createToken('web');

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->fillForm(['password' => 'brand1new1password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_saving_without_a_password_keeps_api_tokens(): void
    {
        $client = $this->client();
        $client->createToken('web');

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $client->tokens()->count());
    }

    public function test_the_set_password_action_revokes_api_tokens(): void
    {
        $client = $this->client();
        $client->createToken('web');

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->callAction('resetPassword', data: [
                'password' => 'brand1new1password',
                'password_confirmation' => 'brand1new1password',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(0, $client->tokens()->count());
    }
}
