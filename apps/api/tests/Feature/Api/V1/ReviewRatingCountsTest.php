<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * meta.rating_counts replaces the five `?rating=N&per_page=1` requests the web
 * made to draw the per-star histogram.
 */
class ReviewRatingCountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $ratings
     */
    private function reviews(Doctor|Facility $reviewable, array $ratings, ReviewStatus $status = ReviewStatus::Approved): void
    {
        foreach ($ratings as $rating) {
            Review::factory()->create([
                'reviewable_type' => $reviewable::class,
                'reviewable_id' => $reviewable->id,
                'rating' => $rating,
                'status' => $status,
                'published_at' => $status === ReviewStatus::Approved ? now() : null,
            ]);
        }
    }

    public function test_counts_only_approved_reviews_of_this_profile_per_star(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $this->reviews($doctor, [5, 5, 5, 4, 2]);
        $this->reviews($doctor, [1, 1, 5], ReviewStatus::Pending);
        $this->reviews($doctor, [1], ReviewStatus::Rejected);
        $this->reviews(Doctor::factory()->create(), [3, 3]);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.rating_counts', ['1' => 0, '2' => 1, '3' => 0, '4' => 1, '5' => 3]);
    }

    public function test_counts_ignore_the_rating_filter_and_the_page(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $this->reviews($doctor, [5, 5, 4, 1]);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews?rating=5&per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.rating_counts', ['1' => 1, '2' => 0, '3' => 0, '4' => 1, '5' => 2]);
    }

    public function test_profiles_without_reviews_get_zeroes(): void
    {
        $facility = Facility::factory()->create(['slug' => 'klinika']);
        $pharmacy = Facility::factory()->pharmacy()->create(['slug' => 'apteka']);
        $this->reviews($pharmacy, [3]);

        $this->getJson('/api/v1/facilities/klinika/reviews')
            ->assertJsonPath('meta.rating_counts', ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0]);
        $this->getJson('/api/v1/pharmacies/apteka/reviews')
            ->assertJsonPath('meta.rating_counts.3', 1);
    }
}
