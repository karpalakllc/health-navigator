<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_only_admin_can_manage_users_in_filament(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole('Administrator');
        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');
        $member = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($moderator->can('viewAny', User::class));
        $this->assertFalse($member->can('viewAny', User::class));
    }
}
