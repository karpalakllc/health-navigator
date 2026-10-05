<?php

namespace Tests\Unit;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * User::canModerateForumTopic() and ForumTopicPolicy::update used to be two
 * copies of the same rule; they had already drifted on a topic without a
 * category (false vs. a TypeError). The model method now delegates.
 */
class ForumTopicModerationParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_topic_without_a_category_is_not_moderatable_by_either_path(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator, 'user_kind' => UserKind::Staff]);
        $moderator->syncRoles(['Moderator']);

        $topic = ForumTopic::factory()->create();
        $topic->setRelation('category', null);

        $this->assertFalse($moderator->can('update', $topic));
        $this->assertFalse($moderator->canModerateForumTopic($topic));
    }

    public function test_both_paths_agree_for_scoped_and_unscoped_moderators(): void
    {
        $mine = ForumCategory::factory()->create();
        $theirs = ForumCategory::factory()->create();
        $inScope = ForumTopic::factory()->create(['forum_category_id' => $mine->getKey()]);
        $outOfScope = ForumTopic::factory()->create(['forum_category_id' => $theirs->getKey()]);

        $scoped = User::factory()->create(['role' => UserRole::Member, 'user_kind' => UserKind::Client]);
        $scoped->assignRole('Forum Moderator');
        $scoped->moderatedForumCategories()->attach($mine);

        $staff = User::factory()->create(['role' => UserRole::Moderator, 'user_kind' => UserKind::Staff]);
        $staff->syncRoles(['Moderator']);

        $member = User::factory()->create(['role' => UserRole::Member, 'user_kind' => UserKind::Client]);

        foreach ([$scoped, $staff, $member] as $user) {
            foreach ([$inScope, $outOfScope] as $topic) {
                $this->assertSame($user->can('update', $topic), $user->canModerateForumTopic($topic));
            }
        }

        $this->assertTrue($scoped->canModerateForumTopic($inScope));
        $this->assertFalse($scoped->canModerateForumTopic($outOfScope));
        $this->assertTrue($staff->canModerateForumTopic($outOfScope));
        $this->assertFalse($member->canModerateForumTopic($inScope));
    }
}
