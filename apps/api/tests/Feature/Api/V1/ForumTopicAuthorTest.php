<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The „Автор“ tag on replies comes from the API, not from the web guessing by
 * display name: two members can share one.
 */
class ForumTopicAuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_replies_say_whether_the_topic_opener_wrote_them(): void
    {
        $opener = User::factory()->create(['display_name' => 'Ана П.']);
        $namesake = User::factory()->create(['display_name' => 'Ана П.']);
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'water',
            'user_id' => $opener->id,
        ]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $opener->id, 'published_at' => now()->subMinutes(2)]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $namesake->id, 'published_at' => now()->subMinute()]);

        $response = $this->getJson('/api/v1/forum/categories/general/topics/water')
            ->assertOk()
            ->assertJsonPath('data.posts.0.is_topic_author', true)
            ->assertJsonPath('data.posts.1.is_topic_author', false)
            ->assertJsonPath('data.posts.1.author_name', 'Ана П.');

        foreach ($response->json('data.posts') as $post) {
            $this->assertArrayNotHasKey('user_id', $post);
            $this->assertArrayNotHasKey('user_id', $post['author']);
            $this->assertArrayNotHasKey('id', $post['author']);
        }
    }

    /**
     * A deleted account's replies are anonymous: the tag would still tie them
     * to the (equally anonymous) opener's topic and to each other.
     */
    public function test_a_deleted_openers_replies_are_not_tagged(): void
    {
        $opener = User::factory()->create();
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'slug' => 'water', 'user_id' => $opener->id]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $opener->id]);
        app(AnonymiseUser::class)->handle($opener);

        $this->getJson('/api/v1/forum/categories/general/topics/water')
            ->assertOk()
            ->assertJsonPath('data.posts.0.is_topic_author', false);
    }

    /**
     * The web hides „Пријави“ on the viewer's own topic and replies; the flag
     * only appears for a signed-in viewer, so anonymous payloads stay alike.
     */
    public function test_a_signed_in_viewer_is_told_which_items_are_their_own(): void
    {
        $opener = User::factory()->create();
        $other = User::factory()->create();
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'slug' => 'water', 'user_id' => $opener->id]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $other->id, 'published_at' => now()->subMinutes(2)]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $opener->id, 'published_at' => now()->subMinute()]);

        $this->getJson('/api/v1/forum/categories/general/topics/water')
            ->assertOk()
            ->assertJsonMissingPath('data.topic.viewer')
            ->assertJsonMissingPath('data.posts.0.viewer');

        $this->withToken($opener->createToken('t')->plainTextToken)
            ->getJson('/api/v1/forum/categories/general/topics/water')
            ->assertOk()
            ->assertJsonPath('data.topic.viewer.is_own', true)
            ->assertJsonMissingPath('data.topic.viewer.can_moderate')
            ->assertJsonPath('data.posts.0.viewer.is_own', false)
            ->assertJsonPath('data.posts.1.viewer.is_own', true);
    }
}
