<?php

namespace Tests\Unit;

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Forum\ForumContentModeration;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForumContentModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_forum_moderator_content_is_auto_approved(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');

        $this->assertSame(
            ForumContentStatus::Approved,
            ForumContentModeration::initialTopicStatus($moderator),
        );
    }

    public function test_member_content_follows_site_setting(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        SiteSetting::current()->update(['forum_topics_require_moderation' => false]);

        $this->assertSame(
            ForumContentStatus::Approved,
            ForumContentModeration::initialTopicStatus($member),
        );

        SiteSetting::current()->update(['forum_topics_require_moderation' => true]);

        $this->assertSame(
            ForumContentStatus::Pending,
            ForumContentModeration::initialTopicStatus($member->fresh()),
        );
    }
}
