<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /forum/topics/unanswered: „Прашања без одговор“ (W8-A).
 */
class ForumUnansweredTest extends TestCase
{
    use RefreshDatabase;

    private ForumCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ForumCategory::factory()->create(['slug' => 'opsto']);
    }

    private function topic(string $slug, array $attributes = []): ForumTopic
    {
        return ForumTopic::factory()->create([
            'forum_category_id' => $this->category->id,
            'slug' => $slug,
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function slugs(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/forum/topics/unanswered'.$query)
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();
    }

    public function test_lists_only_visible_topics_without_a_reply_newest_first(): void
    {
        $this->topic('older', ['published_at' => now()->subDays(3)]);
        $this->topic('newer', ['published_at' => now()->subHour()]);
        $answered = $this->topic('answered');
        ForumPost::factory()->create(['forum_topic_id' => $answered->id]);
        $this->topic('pending')->forceFill(['status' => ForumContentStatus::Pending])->save();
        $this->topic('pinned', ['is_pinned' => true]);
        $this->topic('locked', ['is_locked' => true]);

        $hidden = ForumCategory::factory()->unpublished()->create(['slug' => 'hidden']);
        ForumTopic::factory()->create(['forum_category_id' => $hidden->id, 'slug' => 'in-hidden']);

        $response = $this->getJson('/api/v1/forum/topics/unanswered')->assertOk();

        $this->assertSame(['newer', 'older'], collect($response->json('data'))->pluck('slug')->all());
        $response
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.category.slug', 'opsto')
            ->assertJsonPath('data.0.author_name', fn ($name) => is_string($name) && $name !== '');
    }

    public function test_a_pending_reply_does_not_answer_a_topic(): void
    {
        $topic = $this->topic('waiting');
        ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id]);

        $this->assertSame(['waiting'], $this->slugs());
    }

    public function test_the_authors_own_follow_up_does_not_answer_their_question(): void
    {
        $author = User::factory()->create();
        $topic = $this->topic('follow-up', ['user_id' => $author->id]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $author->id]);

        $this->assertSame(['follow-up'], $this->slugs());

        ForumPost::factory()->create(['forum_topic_id' => $topic->id]);

        $this->assertSame([], $this->slugs());
    }

    public function test_filters_by_category_and_minimum_age(): void
    {
        $other = ForumCategory::factory()->create(['slug' => 'ishrana']);
        $this->topic('fresh', ['published_at' => now()->subMinutes(30)]);
        $this->topic('day-old', ['published_at' => now()->subDay()]);
        ForumTopic::factory()->create(['forum_category_id' => $other->id, 'slug' => 'elsewhere']);

        $this->assertSame(['elsewhere'], $this->slugs('?category=ishrana'));
        $this->assertSame(['day-old'], $this->slugs('?category=opsto&min_age_hours=2'));
        $this->getJson('/api/v1/forum/topics/unanswered?category=nema')->assertNotFound();
        $this->getJson('/api/v1/forum/topics/unanswered?per_page=21')->assertUnprocessable();
    }

    public function test_searches_titles(): void
    {
        $this->topic('pritisok', ['title' => 'Висок притисок наутро']);
        $this->topic('son', ['title' => 'Лош сон']);

        $this->assertSame(['pritisok'], $this->slugs('?q='.rawurlencode('притисок')));
    }

    public function test_cached_list_drops_a_topic_as_soon_as_it_is_answered(): void
    {
        $topic = $this->topic('soon-answered');

        $this->assertSame(['soon-answered'], $this->slugs());

        // Approval bumps replies_count through increment(), which saves no
        // model: the cache must still forget the topic.
        $reply = ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id]);
        $reply->approve(User::factory()->create());

        $this->assertSame([], $this->slugs());
    }

    public function test_cached_list_brings_a_topic_back_when_its_only_answer_is_taken_down(): void
    {
        $topic = $this->topic('answer-removed');
        $reply = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);

        $this->assertSame([], $this->slugs());

        $reply->reject(User::factory()->create(), afterReport: true);
        $topic->recordRemovedReply();

        $this->assertSame(['answer-removed'], $this->slugs());
    }

    public function test_cached_list_follows_a_topic_being_locked(): void
    {
        $topic = $this->topic('to-lock');

        $this->assertSame(['to-lock'], $this->slugs());

        $topic->update(['is_locked' => true]);

        $this->assertSame([], $this->slugs());
    }

    public function test_is_public_and_follows_the_forum_switch(): void
    {
        $this->topic('any');

        $response = $this->getJson('/api/v1/forum/topics/unanswered')->assertOk();
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));

        SiteSetting::current()->update(['public_forum' => false]);

        $this->getJson('/api/v1/forum/topics/unanswered')->assertStatus(503);
    }

    public function test_reports_no_view_or_viewer_data(): void
    {
        $this->topic('shape');

        $item = $this->getJson('/api/v1/forum/topics/unanswered')->json('data.0');

        $this->assertEqualsCanonicalizing(
            ['slug', 'title', 'excerpt', 'author_name', 'replies_count', 'last_post_at', 'published_at', 'category'],
            array_keys($item),
        );
    }
}
