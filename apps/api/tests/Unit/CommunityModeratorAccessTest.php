<?php

namespace Tests\Unit;

use App\Enums\UserKind;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommunityModeratorAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_forum_moderator_can_access_panel_without_admin_access(): void
    {
        $moderator = User::factory()->create(['user_kind' => UserKind::Client]);
        $moderator->assignRole('Forum Moderator');

        $this->assertFalse($moderator->can('admin.access'));
        $this->assertTrue($moderator->canAccessPanel(Panel::make('admin')));
        $this->assertTrue($moderator->isCommunityModeratorOnly());
    }

    public function test_staff_moderator_has_admin_access_and_is_not_community_only(): void
    {
        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');

        $this->assertTrue($moderator->can('admin.access'));
        $this->assertFalse($moderator->isCommunityModeratorOnly());
    }
}
