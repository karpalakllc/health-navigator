<?php

namespace Tests\Feature\Api\V1\Usernames;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner decision 2026-10-14: every public surface shows the member's unique
 * username. Never the real name, and no longer the „Име П.“ display name —
 * initials can be enough for a doctor to recognise the reviewer.
 */
class PublicNameTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE = ['Марија', 'Костовска', 'Марија К.', 'marija@example.com'];

    private function member(): User
    {
        $member = User::factory()->create([
            'name' => 'Марија Костовска',
            'username' => 'bitolchanka',
            'email' => 'marija@example.com',
        ]);
        $member->forceFill(['display_name' => 'Марија К.'])->save();

        return $member;
    }

    private function assertNothingPrivate(string $content): void
    {
        foreach (self::PRIVATE as $private) {
            $this->assertStringNotContainsString($private, $content);
        }
    }

    public function test_reviews_show_the_username(): void
    {
        SiteSetting::current();
        $member = $this->member();
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        $facility = Facility::factory()->create(['slug' => 'klinika', 'is_published' => true]);

        foreach ([$doctor, $facility] as $reviewable) {
            Review::factory()->approved()->create([
                'user_id' => $member->id,
                'reviewable_type' => $reviewable::class,
                'reviewable_id' => $reviewable->id,
                'published_at' => now(),
            ]);
        }

        $responses = [
            $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertOk()->assertJsonPath('data.0.author_name', 'bitolchanka'),
            $this->getJson('/api/v1/facilities/klinika/reviews')->assertOk()->assertJsonPath('data.0.author_name', 'bitolchanka'),
            $this->getJson('/api/v1/home/highlights')->assertOk()->assertJsonPath('data.recent_reviews.0.author_name', 'bitolchanka'),
        ];

        foreach ($responses as $response) {
            $this->assertNothingPrivate((string) $response->getContent());
        }
    }

    public function test_forum_lists_topics_and_posts_show_the_username(): void
    {
        $member = $this->member();
        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'user_id' => $member->id,
            'slug' => 'help-topic',
            'title' => 'Hydration question',
        ]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $member->id]);

        $responses = [
            $this->getJson('/api/v1/forum/categories/general/topics')->assertOk()->assertJsonPath('data.0.author_name', 'bitolchanka'),
            $this->getJson('/api/v1/forum/topics?q=hydration')->assertOk()->assertJsonPath('data.0.author_name', 'bitolchanka'),
            $this->getJson('/api/v1/forum/topics/recent')->assertOk(),
            $this->getJson('/api/v1/forum/categories/general/topics/help-topic')
                ->assertOk()
                ->assertJsonPath('data.topic.author_name', 'bitolchanka')
                ->assertJsonPath('data.topic.author.name', 'bitolchanka')
                ->assertJsonPath('data.posts.0.author_name', 'bitolchanka')
                ->assertJsonPath('data.posts.0.author.name', 'bitolchanka'),
        ];

        foreach ($responses as $response) {
            $this->assertNothingPrivate((string) $response->getContent());
        }
    }

    /**
     * Structural guard: a public resource names a member only through
     * User::publicName(), never `name` or the retired `display_name`.
     */
    public function test_public_resources_name_members_only_through_public_name(): void
    {
        $files = glob(app_path('Http/Resources/Api/V1/*.php')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('display_name', $source, basename($file));
            $this->assertDoesNotMatchRegularExpression('/->user->name\b|->author->name\b|\$this->user\?->name\b/', $source, basename($file));
        }
    }
}
