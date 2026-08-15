<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_categories(): void
    {
        ForumCategory::factory()->create(['slug' => 'general', 'is_published' => true]);
        ForumCategory::factory()->unpublished()->create(['slug' => 'hidden']);

        $this->getJson('/api/v1/forum/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'general');
    }

    public function test_global_topic_search_returns_matching_approved_topics(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'nutrition', 'name' => 'Исхрана']);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'hydration-summer',
            'title' => 'Summer hydration tips',
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'other-topic',
            'title' => 'Other discussion',
        ]);
        ForumTopic::factory()->pending()->create([
            'forum_category_id' => $category->id,
            'slug' => 'pending-topic',
            'title' => 'Pending hydration topic',
        ]);

        $this->getJson('/api/v1/forum/topics?q=hydration')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'hydration-summer')
            ->assertJsonPath('data.0.category.slug', 'nutrition');
    }

    public function test_global_topic_search_requires_minimum_query_length(): void
    {
        ForumCategory::factory()->create();
        ForumTopic::factory()->create(['title' => 'Visible topic']);

        $this->getJson('/api/v1/forum/topics?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_lists_approved_topics_in_category(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'visible-topic',
            'title' => 'Visible topic',
        ]);
        ForumTopic::factory()->pending()->create([
            'forum_category_id' => $category->id,
            'slug' => 'pending-topic',
        ]);

        $this->getJson('/api/v1/forum/categories/general/topics')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'visible-topic');
    }

    public function test_topic_detail_shows_only_approved_posts(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'help-topic',
        ]);

        ForumPost::factory()->create([
            'forum_topic_id' => $topic->id,
            'body' => 'Approved reply content here.',
        ]);
        ForumPost::factory()->pending()->create([
            'forum_topic_id' => $topic->id,
            'body' => 'Pending reply should not show.',
        ]);

        $this->getJson('/api/v1/forum/categories/general/topics/help-topic')
            ->assertOk()
            ->assertJsonPath('data.topic.slug', 'help-topic')
            ->assertJsonStructure([
                'data' => [
                    'topic' => [
                        'author' => ['name', 'member_since', 'topics_count', 'posts_count'],
                    ],
                    'posts' => [
                        ['author' => ['name', 'topics_count', 'posts_count']],
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.posts');
    }

    public function test_member_can_create_topic_pending_moderation(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'New discussion topic',
            'body' => 'This is the opening post body with enough length.',
            'accepted_community_rules' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'status' => ForumContentStatus::Pending->value,
        ]);
    }

    public function test_forum_moderator_topics_publish_immediately(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ForumCategory::factory()->create(['slug' => 'general']);
        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');

        Sanctum::actingAs($moderator);

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Moderator announcement',
            'body' => 'This topic should be visible without staff approval.',
            'accepted_community_rules' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_member_topics_publish_immediately_when_moderation_disabled(): void
    {
        ForumCategory::factory()->create(['slug' => 'general']);
        SiteSetting::current()->update(['forum_topics_require_moderation' => false]);

        $member = User::factory()->create(['role' => UserRole::Member]);
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Instant topic',
            'body' => 'This topic should publish without staff approval.',
            'accepted_community_rules' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_forum_moderator_can_pin_and_lock_topic_via_api(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'mod-target',
        ]);
        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');

        Sanctum::actingAs($moderator);

        $this->patchJson('/api/v1/forum/categories/general/topics/mod-target/moderation', [
            'is_pinned' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->patchJson('/api/v1/forum/categories/general/topics/mod-target/moderation', [
            'is_locked' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_locked', true);

        $this->assertDatabaseHas('forum_topics', [
            'id' => $topic->id,
            'is_pinned' => true,
            'is_locked' => true,
        ]);
    }

    public function test_member_cannot_moderate_topic_via_api(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => ForumCategory::query()->value('id'),
            'slug' => 'protected-topic',
        ]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/forum/categories/general/topics/protected-topic/moderation', [
            'is_pinned' => true,
        ])->assertForbidden();
    }

    public function test_topic_detail_includes_viewer_moderation_for_forum_moderator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'visible-topic',
        ]);
        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');
        $token = $moderator->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/forum/categories/general/topics/visible-topic')
            ->assertOk()
            ->assertJsonPath('data.topic.viewer.can_moderate', true);
    }

    public function test_forum_author_shows_forum_moderator_badge(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $moderator = User::factory()->create(['role' => UserRole::Member]);
        $moderator->assignRole('Forum Moderator');
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'mod-authored',
            'user_id' => $moderator->id,
        ]);

        $this->getJson('/api/v1/forum/categories/general/topics/mod-authored')
            ->assertOk()
            ->assertJsonPath('data.topic.author.is_forum_moderator', true);
    }

    public function test_cannot_reply_on_locked_topic(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->locked()->create([
            'forum_category_id' => $category->id,
            'slug' => 'locked-topic',
        ]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/general/topics/locked-topic/posts', [
            'body' => 'Attempting to reply on locked topic.',
        ])->assertUnprocessable();
    }

    public function test_recent_topics_returns_latest_approved(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'older-topic',
            'title' => 'Older topic',
            'last_post_at' => now()->subDay(),
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'newer-topic',
            'title' => 'Newer topic',
            'last_post_at' => now(),
        ]);
        ForumTopic::factory()->pending()->create([
            'forum_category_id' => $category->id,
            'slug' => 'pending-topic',
            'title' => 'Pending topic',
        ]);

        $this->getJson('/api/v1/forum/topics/recent')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'newer-topic')
            ->assertJsonPath('data.0.excerpt', fn ($value) => is_string($value));
    }

    public function test_global_topic_search_can_filter_by_category(): void
    {
        $nutrition = ForumCategory::factory()->create(['slug' => 'nutrition']);
        $general = ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => $nutrition->id,
            'slug' => 'hydration-tips',
            'title' => 'Hydration tips in nutrition',
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $general->id,
            'slug' => 'hydration-general',
            'title' => 'Hydration tips in general',
        ]);

        $this->getJson('/api/v1/forum/topics?q=hydration&category=nutrition')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'hydration-tips');
    }

    public function test_category_topics_can_sort_by_active(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'quiet-topic',
            'title' => 'Quiet topic',
            'replies_count' => 1,
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'busy-topic',
            'title' => 'Busy topic',
            'replies_count' => 12,
        ]);

        $this->getJson('/api/v1/forum/categories/general/topics?sort=active')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'busy-topic');
    }

    public function test_topic_detail_includes_related_topics(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'main-topic',
            'title' => 'Main topic',
        ]);
        ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'related-one',
            'title' => 'Related one',
        ]);

        $this->getJson('/api/v1/forum/categories/general/topics/main-topic')
            ->assertOk()
            ->assertJsonCount(1, 'data.related_topics')
            ->assertJsonPath('data.related_topics.0.slug', 'related-one');
    }

    public function test_topic_creation_requires_accepted_community_rules(): void
    {
        ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'Rules not accepted',
            'body' => 'Opening body with sufficient length for validation.',
        ])->assertUnprocessable();
    }

    public function test_my_forum_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/me/forum/topics')->assertUnauthorized();
        $this->getJson('/api/v1/me/forum/posts')->assertUnauthorized();
    }

    public function test_forum_topic_creation_is_rate_limited(): void
    {
        $this->forgetRateLimits();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/forum/categories/general/topics', [
                'title' => "Topic number {$i} for testing",
                'body' => 'Opening body with sufficient length for validation.',
                'accepted_community_rules' => true,
            ])->assertCreated();
        }

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'One topic too many',
            'body' => 'Opening body with sufficient length for validation.',
            'accepted_community_rules' => true,
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }
}
