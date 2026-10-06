<?php

namespace Tests\Feature\Filament;

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

        $this->actingAs(User::factory()->admin()->create());
    }

    private function staffMember(): User
    {
        return User::factory()->moderator()->create();
    }

    private function client(): User
    {
        return User::factory()->create();
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
