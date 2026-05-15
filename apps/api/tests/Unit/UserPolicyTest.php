<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_users_in_filament(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $member = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($moderator->can('viewAny', User::class));
        $this->assertFalse($member->can('viewAny', User::class));
    }
}
