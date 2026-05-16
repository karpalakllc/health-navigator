<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
    }

    public function test_user_can_register_when_enabled(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Member',
            'email' => 'member@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'member@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'member@example.com',
            'user_kind' => 'client',
        ]);
    }

    public function test_register_is_blocked_when_disabled(): void
    {
        SiteSetting::current()->update(['registrations_enabled' => false]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden();
        $this->assertNull(User::query()->where('email', 'blocked@example.com')->first());
    }
}
