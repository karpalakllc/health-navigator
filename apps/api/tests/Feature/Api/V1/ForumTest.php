<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
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
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('forum_topics', [
            'forum_category_id' => $category->id,
            'status' => ForumContentStatus::Pending->value,
        ]);
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

    public function test_my_forum_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/me/forum/topics')->assertUnauthorized();
        $this->getJson('/api/v1/me/forum/posts')->assertUnauthorized();
    }

    public function test_forum_topic_creation_is_rate_limited(): void
    {
        RateLimiter::clear('api-forum-topics');

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/forum/categories/general/topics', [
                'title' => "Topic number {$i} for testing",
                'body' => 'Opening body with sufficient length for validation.',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/forum/categories/general/topics', [
            'title' => 'One topic too many',
            'body' => 'Opening body with sufficient length for validation.',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }
}
