<?php

namespace Tests\Unit;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\ForumModerationScope;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForumModerationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_scoped_moderator_only_sees_assigned_category_topics(): void
    {
        $cardiology = ForumCategory::factory()->create(['name' => 'Cardiology', 'slug' => 'cardiology']);
        $dermatology = ForumCategory::factory()->create(['name' => 'Dermatology', 'slug' => 'dermatology']);

        ForumTopic::factory()->create([
            'forum_category_id' => $cardiology->id,
            'status' => ForumContentStatus::Pending,
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $dermatology->id,
            'status' => ForumContentStatus::Pending,
        ]);

        $moderator = User::factory()->create(['user_kind' => UserKind::Client]);
        $moderator->assignRole('Forum Moderator');
        $moderator->moderatedForumCategories()->sync([$cardiology->id]);

        $visible = ForumModerationScope::restrictTopics(ForumTopic::query(), $moderator)->pluck('forum_category_id');

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->contains($cardiology->id));
    }

    public function test_scoped_moderator_only_sees_posts_in_assigned_categories(): void
    {
        $allowed = ForumCategory::factory()->create();
        $other = ForumCategory::factory()->create();

        $allowedTopic = ForumTopic::factory()->create(['forum_category_id' => $allowed->id]);
        $otherTopic = ForumTopic::factory()->create(['forum_category_id' => $other->id]);

        ForumPost::factory()->create([
            'forum_topic_id' => $allowedTopic->id,
            'status' => ForumContentStatus::Pending,
        ]);
        ForumPost::factory()->create([
            'forum_topic_id' => $otherTopic->id,
            'status' => ForumContentStatus::Pending,
        ]);

        $moderator = User::factory()->create(['user_kind' => UserKind::Client]);
        $moderator->assignRole('Forum Moderator');
        $moderator->moderatedForumCategories()->sync([$allowed->id]);

        $this->assertSame(1, ForumModerationScope::restrictPosts(ForumPost::query(), $moderator)->count());
    }
}
