<?php

namespace Tests\Feature\Api\V1;

use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ReviewHelpfulVotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * „Корисно“: one vote per member per review, toggled, with a denormalised
 * count that cannot drift.
 */
class ReviewHelpfulTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
    }

    private function review(array $attributes = []): Review
    {
        return Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $this->doctor->id,
            ...$attributes,
        ]);
    }

    public function test_pharmacy_reviews_cannot_be_voted_on_while_the_module_is_off(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create(['is_published' => true]);
        $review = Review::factory()->approved()->create(['reviewable_type' => Facility::class, 'reviewable_id' => $pharmacy->id]);
        SiteSetting::current()->update(['public_pharmacies' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertNotFound();
        $this->deleteJson("/api/v1/reviews/{$review->id}/helpful")->assertNotFound();

        SiteSetting::current()->update(['public_pharmacies' => true]);
        $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertOk();
    }

    public function test_a_member_marks_and_unmarks_a_review_idempotently(): void
    {
        $review = $this->review();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/reviews/{$review->id}/helpful")
            ->assertOk()
            ->assertExactJson(['data' => ['helpful_count' => 1, 'has_voted_helpful' => true]]);
        $this->putJson("/api/v1/reviews/{$review->id}/helpful")
            ->assertOk()
            ->assertJsonPath('data.helpful_count', 1);
        $this->assertSame(1, $review->fresh()->helpful_count);
        $this->assertDatabaseCount('review_helpful_votes', 1);

        $this->deleteJson("/api/v1/reviews/{$review->id}/helpful")
            ->assertOk()
            ->assertExactJson(['data' => ['helpful_count' => 0, 'has_voted_helpful' => false]]);
        $this->deleteJson("/api/v1/reviews/{$review->id}/helpful")
            ->assertOk()
            ->assertJsonPath('data.helpful_count', 0);
        $this->assertSame(0, $review->fresh()->helpful_count);
        $this->assertDatabaseCount('review_helpful_votes', 0);
    }

    public function test_votes_from_several_members_add_up(): void
    {
        $review = $this->review();

        foreach (range(1, 3) as $ignored) {
            ReviewHelpfulVotes::add($review, User::factory()->create());
        }

        $this->assertSame(3, $review->fresh()->helpful_count);
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertJsonPath('data.0.helpful_count', 3);
    }

    public function test_members_cannot_vote_on_their_own_review(): void
    {
        $author = User::factory()->create();
        $review = $this->review(['user_id' => $author->id]);
        Sanctum::actingAs($author);

        $this->putJson("/api/v1/reviews/{$review->id}/helpful")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
        $this->assertSame(0, $review->fresh()->helpful_count);
    }

    public function test_only_published_reviews_take_votes(): void
    {
        $pending = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/reviews/{$pending->id}/helpful")->assertNotFound();
        $this->assertDatabaseCount('review_helpful_votes', 0);
    }

    public function test_signed_out_visitors_and_accounts_without_the_member_role_cannot_vote(): void
    {
        $review = $this->review();

        $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->staff()->create());
        $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertForbidden();

        $this->assertSame(0, $review->fresh()->helpful_count);
    }

    public function test_votes_are_throttled(): void
    {
        $review = $this->review();
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->putJson("/api/v1/reviews/{$review->id}/helpful")->assertOk();
        }

        $this->deleteJson("/api/v1/reviews/{$review->id}/helpful")->assertTooManyRequests();
    }

    public function test_viewer_state_is_present_only_for_signed_in_requests(): void
    {
        $voted = $this->review(['published_at' => now()->subDay()]);
        $this->review(['published_at' => now()]);
        $member = User::factory()->create();
        ReviewHelpfulVotes::add($voted, $member);

        $anonymous = $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertOk();
        foreach ($anonymous->json('data') as $item) {
            $this->assertArrayNotHasKey('viewer', $item);
        }

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($member);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.viewer.has_voted_helpful', false)
            ->assertJsonPath('data.1.viewer.has_voted_helpful', true)
            ->assertJsonPath('data.1.helpful_count', 1);
    }

    public function test_the_list_can_sort_by_most_helpful(): void
    {
        $quiet = $this->review(['published_at' => now()]);
        $popular = $this->review(['published_at' => now()->subWeek()]);
        ReviewHelpfulVotes::add($popular, User::factory()->create());
        ReviewHelpfulVotes::add($popular, User::factory()->create());

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?sort=helpful')
            ->assertOk()
            ->assertJsonPath('data.0.id', $popular->id)
            ->assertJsonPath('data.1.id', $quiet->id);
    }

    /** Equal counts and dates: pages must not swap rows between requests (as fe1e985). */
    public function test_helpful_ties_are_broken_by_newest_id(): void
    {
        $at = now()->subDay();
        $ids = collect(range(1, 3))->map(fn () => $this->review(['published_at' => $at])->id);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?sort=helpful')
            ->assertOk()
            ->assertJsonPath('data.*.id', $ids->sortDesc()->values()->all());
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?sort=oldest')
            ->assertOk()
            ->assertJsonPath('data.*.id', $ids->values()->all());
    }

    public function test_a_hidden_review_drops_out_with_its_votes(): void
    {
        $review = $this->review();
        $member = User::factory()->create();
        ReviewHelpfulVotes::add($review, $member);

        ContentReport::factory()->about($review)->create()->hideContent(User::factory()->moderator()->create());

        // Only its placeholder remains (W5-I), without the vote count.
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.removed', true)
            ->assertJsonMissingPath('data.0.helpful_count');
        Sanctum::actingAs($member);
        $this->deleteJson("/api/v1/reviews/{$review->id}/helpful")->assertNotFound();
    }
}
