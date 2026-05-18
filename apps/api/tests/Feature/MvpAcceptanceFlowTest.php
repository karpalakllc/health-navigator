<?php

namespace Tests\Feature;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end API slice for MVP-5: publish directory → member UGC → moderate → public visibility.
 */
class MvpAcceptanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_doctor_review_moderation_and_public_visibility(): void
    {
        $doctor = Doctor::factory()->unpublished()->create([
            'slug' => 'mvp-accept-doc',
            'full_name' => 'MVP Accept Doctor',
        ]);

        $this->getJson('/api/v1/doctors/mvp-accept-doc')
            ->assertNotFound();

        $doctor->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/doctors/mvp-accept-doc')
            ->assertOk()
            ->assertJsonPath('data.slug', 'mvp-accept-doc');

        $member = User::factory()->create(['role' => UserRole::Member]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/doctors/mvp-accept-doc/reviews', [
            'rating' => 5,
            'body' => 'Walkthrough review with enough characters for validation.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/v1/doctors/mvp-accept-doc/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $review = Review::query()->where('user_id', $member->id)->sole();
        $review->approve($moderator);

        $this->getJson('/api/v1/doctors/mvp-accept-doc/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.body', 'Walkthrough review with enough characters for validation.');

        $this->getJson('/api/v1/doctors/mvp-accept-doc')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 1)
            ->assertJsonPath('data.review_summary.average_rating', 5);
    }

    public function test_forum_topic_moderation_and_public_visibility(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'mvp-accept-cat']);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/forum/categories/mvp-accept-cat/topics', [
            'title' => 'MVP acceptance forum topic',
            'body' => 'Opening post body with sufficient length for validation rules.',
            'accepted_community_rules' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/v1/forum/categories/mvp-accept-cat/topics')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $topic = ForumTopic::query()->where('user_id', $member->id)->sole();
        $this->assertSame(ForumContentStatus::Pending, $topic->status);

        $topic->approve($moderator);

        $this->getJson('/api/v1/forum/categories/mvp-accept-cat/topics')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $topic->slug);

        $this->getJson("/api/v1/forum/categories/mvp-accept-cat/topics/{$topic->slug}")
            ->assertOk()
            ->assertJsonPath('data.topic.title', 'MVP acceptance forum topic');
    }
}
