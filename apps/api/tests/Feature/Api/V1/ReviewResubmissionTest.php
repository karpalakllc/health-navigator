<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RemovalCategory;
use App\Enums\ReviewAspect;
use App\Enums\ReviewStatus;
use App\Mail\UgcSubmittedMail;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner decision (2026-10-14): a review refused before publication may be
 * edited and sent to moderation once more. A second refusal is final; a review
 * removed after publication keeps its placeholder and is never resent.
 */
class ReviewResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $member;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $this->member = User::factory()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    private function pendingReview(): Review
    {
        $review = Review::factory()->create([
            'user_id' => $this->member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $this->doctor->id,
            'rating' => 1,
            'body' => 'Првиот текст со лични податоци.',
            'status' => ReviewStatus::Pending,
        ]);
        $review->aspectRatings()->create(['aspect' => ReviewAspect::Communication, 'rating' => 1]);

        return $review;
    }

    private function send(array $payload = []): TestResponse
    {
        Sanctum::actingAs($this->member);

        return $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 3,
            'body' => 'Изменет текст без лични податоци.',
            ...$payload,
        ]);
    }

    public function test_a_refused_review_can_be_edited_and_sent_again_once(): void
    {
        Mail::fake();
        $review = $this->pendingReview();
        $review->reject($this->moderator, 'Наведовте име на друг пациент.');

        $this->send(['aspects' => ['waiting_time' => 4]])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.resubmitted', true)
            ->assertJsonPath('data.rating', 3);

        $fresh = $review->fresh();
        $this->assertSame(ReviewStatus::Pending, $fresh->status);
        $this->assertSame('Изменет текст без лични податоци.', $fresh->body);
        $this->assertSame(3, $fresh->rating);
        $this->assertSame(1, $fresh->resubmission_count);
        $this->assertNotNull($fresh->resubmitted_at);
        $this->assertNull($fresh->rejection_note);
        $this->assertNull($fresh->moderated_by_id);
        // The same row: still one review per member per profile.
        $this->assertSame(1, Review::query()->where('user_id', $this->member->id)->count());
        $this->assertSame(['waiting_time' => 4], $fresh->aspectRatings()->get()
            ->mapWithKeys(fn ($a): array => [$a->aspect->value => $a->rating])->all());
        Mail::assertQueued(UgcSubmittedMail::class);
    }

    public function test_a_second_refusal_is_final(): void
    {
        $review = $this->pendingReview();
        $review->reject($this->moderator, 'Прво одбивање.');
        $this->send()->assertOk();
        $review->fresh()->reject($this->moderator, 'Второ одбивање.');

        $this->send(['body' => 'Трет обид со нов текст.'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.review.0', __('api.review.resubmission_used'));

        $this->assertSame(ReviewStatus::Rejected, $review->fresh()->status);
        $this->assertSame('Второ одбивање.', $review->fresh()->rejection_note);
    }

    public function test_a_review_removed_after_publication_is_never_resent(): void
    {
        $review = $this->pendingReview();
        $review->approve($this->moderator);
        $review->fresh()->reject($this->moderator, 'Навреда.', category: RemovalCategory::Abuse);

        $this->send()
            ->assertUnprocessable()
            ->assertJsonPath('errors.review.0', __('api.review.duplicate'));

        $fresh = $review->fresh();
        $this->assertTrue($fresh->isRemoved());
        $this->assertSame('Првиот текст со лични податоци.', $fresh->body);
        $this->assertSame(0, $fresh->resubmission_count);
    }

    public function test_pending_and_published_reviews_are_still_duplicates(): void
    {
        $review = $this->pendingReview();
        $this->send()->assertUnprocessable()->assertJsonPath('errors.review.0', __('api.review.duplicate'));

        $review->approve($this->moderator);
        $this->send()->assertUnprocessable()->assertJsonPath('errors.review.0', __('api.review.duplicate'));

        $this->assertSame(1, $review->fresh()->rating);
    }

    public function test_the_viewer_sees_the_reason_and_whether_they_may_resend(): void
    {
        $review = $this->pendingReview();
        $review->reject($this->moderator, 'Наведовте име на друг пациент.');
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.viewer_review.status', 'rejected')
            ->assertJsonPath('meta.viewer_review.rejection_note', 'Наведовте име на друг пациент.')
            ->assertJsonPath('meta.viewer_review.can_resubmit', true)
            ->assertJsonPath('meta.viewer_review.removed', false)
            ->assertJsonPath('meta.viewer_review.body', 'Првиот текст со лични податоци.')
            ->assertJsonPath('meta.viewer_review.aspects.communication', 1);

        $this->getJson('/api/v1/me/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.can_resubmit', true);

        $this->send()->assertOk();
        $review->fresh()->reject($this->moderator, 'Второ одбивање.');

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertJsonPath('meta.viewer_review.can_resubmit', false)
            ->assertJsonPath('meta.viewer_review.rejection_note', 'Второ одбивање.');
        $this->getJson('/api/v1/me/reviews')->assertJsonPath('data.0.can_resubmit', false);
    }

    public function test_a_resent_review_publishes_like_any_other(): void
    {
        $review = $this->pendingReview();
        $review->reject($this->moderator, 'Прво одбивање.');
        $this->send()->assertOk();

        $review->fresh()->approve($this->moderator);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Изменет текст без лични податоци.')
            ->assertJsonPath('data.0.rating', 3);
        $this->assertSame(3.0, (float) $this->doctor->fresh()->rating_avg);
    }
}
