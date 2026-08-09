<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regressions for the moderation defects found in the Part I audit: content
 * approved at creation used to skip all publication bookkeeping (H1), the
 * moderation endpoint disagreed with the UI about who may moderate (H2), and
 * category-scoped moderators could act outside their categories (H8).
 */
class ForumModerationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        RateLimiter::clear('api-forum-topics');
        RateLimiter::clear('api-forum-posts');
    }

    private function openModeration(): void
    {
        SiteSetting::current()->update([
            'forum_topics_require_moderation' => false,
            'forum_posts_require_moderation' => false,
        ]);
    }

    // ---- H1: publication bookkeeping on the auto-approve path ----

    public function test_auto_approved_topic_is_published_and_has_activity_timestamp(): void
    {
        $this->openModeration();

        $category = ForumCategory::factory()->create(['is_published' => true]);
        $author = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($author)
            ->postJson("/api/v1/forum/categories/{$category->slug}/topics", [
                'title' => 'Prashanje za vakcinacija',
                'body' => str_repeat('Detalen opis na prashanjeto. ', 3),
                'accepted_community_rules' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');

        $topic = ForumTopic::query()->sole();

        $this->assertNotNull($topic->published_at, 'Auto-approved topic must be stamped as published.');
        $this->assertNotNull($topic->last_post_at, 'Reply-less topics need an activity timestamp for ordering.');
    }

    public function test_auto_approved_reply_publishes_and_increments_topic_counters(): void
    {
        $this->openModeration();

        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'replies_count' => 0,
        ]);
        $author = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($author)
            ->postJson("/api/v1/forum/categories/{$category->slug}/topics/{$topic->slug}/posts", [
                'body' => str_repeat('Blagodaram za odgovorot. ', 3),
            ])
            ->assertCreated();

        $post = ForumPost::query()->sole();
        $topic->refresh();

        $this->assertNotNull($post->published_at);
        $this->assertSame(1, $topic->replies_count);
        $this->assertNotNull($topic->last_post_at);
    }

    public function test_topic_listing_reports_the_reply_count_for_auto_approved_replies(): void
    {
        $this->openModeration();

        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);
        $author = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($author)->postJson(
            "/api/v1/forum/categories/{$category->slug}/topics/{$topic->slug}/posts",
            ['body' => str_repeat('Sodrzhina na odgovorot. ', 3)],
        )->assertCreated();

        $this->getJson("/api/v1/forum/categories/{$category->slug}/topics")
            ->assertOk()
            ->assertJsonPath('data.0.replies_count', 1)
            ->assertJsonPath('data.0.published_at', fn ($value) => $value !== null);
    }

    public function test_moderator_approval_increments_the_counter_exactly_once(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'replies_count' => 0,
        ]);
        $post = ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id]);

        $moderator = User::factory()->create([
            'role' => UserRole::Moderator,
            'user_kind' => UserKind::Staff,
        ]);
        $moderator->assignRole('Moderator');

        $post->approve($moderator);
        $topic->refresh();

        $this->assertSame(1, $topic->replies_count);

        // Re-approving an already-approved post must not double count.
        $post->approve($moderator);
        $topic->refresh();

        $this->assertSame(1, $topic->replies_count);
    }

    // ---- H2: staff moderators may use the public moderation endpoint ----

    public function test_staff_moderator_can_moderate_via_the_api(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);

        $moderator = User::factory()->create([
            'role' => UserRole::Moderator,
            'user_kind' => UserKind::Staff,
        ]);
        $moderator->assignRole('Moderator');

        $this->actingAs($moderator)
            ->patchJson(
                "/api/v1/forum/categories/{$category->slug}/topics/{$topic->slug}/moderation",
                ['is_locked' => true],
            )
            ->assertOk()
            ->assertJsonPath('data.is_locked', true);
    }

    public function test_plain_member_still_cannot_moderate(): void
    {
        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)
            ->patchJson(
                "/api/v1/forum/categories/{$category->slug}/topics/{$topic->slug}/moderation",
                ['is_locked' => true],
            )
            ->assertForbidden();
    }

    // ---- H8: category-scoped moderation is enforced on writes ----

    public function test_scoped_moderator_cannot_moderate_outside_assigned_categories(): void
    {
        $assigned = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'kardiologija']);
        $other = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'dermatologija']);

        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');
        $moderator->moderatedForumCategories()->sync([$assigned->id]);

        $inScope = ForumTopic::factory()->create(['forum_category_id' => $assigned->id]);
        $outOfScope = ForumTopic::factory()->create(['forum_category_id' => $other->id]);

        $this->actingAs($moderator)
            ->patchJson(
                "/api/v1/forum/categories/{$assigned->slug}/topics/{$inScope->slug}/moderation",
                ['is_locked' => true],
            )
            ->assertOk();

        $this->actingAs($moderator)
            ->patchJson(
                "/api/v1/forum/categories/{$other->slug}/topics/{$outOfScope->slug}/moderation",
                ['is_locked' => true],
            )
            ->assertForbidden();

        $this->assertFalse($outOfScope->fresh()->is_locked);
    }

    public function test_scoped_moderator_is_not_offered_a_toolbar_outside_scope(): void
    {
        $assigned = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'kardiologija']);
        $other = ForumCategory::factory()->create(['is_published' => true, 'slug' => 'dermatologija']);

        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');
        $moderator->moderatedForumCategories()->sync([$assigned->id]);

        $outOfScope = ForumTopic::factory()->create(['forum_category_id' => $other->id]);
        $token = $moderator->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/forum/categories/{$other->slug}/topics/{$outOfScope->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.topic.viewer');
    }
}
