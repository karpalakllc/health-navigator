<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_route_allows_admin_and_moderator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Moderator]));

        $this->getJson('/api/v1/platform/staff')
            ->assertOk()
            ->assertJsonPath('data.role', 'moderator');
    }

    public function test_staff_route_forbids_member(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Member]));

        $this->getJson('/api/v1/platform/staff')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_admin_route_allows_only_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->getJson('/api/v1/platform/admin')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_admin_route_forbids_moderator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Moderator]));

        $this->getJson('/api/v1/platform/admin')->assertForbidden();
    }
}
