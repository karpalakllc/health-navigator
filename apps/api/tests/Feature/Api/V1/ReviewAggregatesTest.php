<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use App\Support\ReviewAggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * doctors/facilities.reviews_count and rating_avg are recomputed from the
 * approved reviews whenever a review is created, moderated, edited or deleted.
 */
class ReviewAggregatesTest extends TestCase
{
    use RefreshDatabase;

    private function review(Doctor|Facility $target, int $rating, ReviewStatus $status = ReviewStatus::Pending): Review
    {
        return Review::factory()->create([
            'reviewable_type' => $target::class,
            'reviewable_id' => $target->id,
            'rating' => $rating,
            'status' => $status,
            'published_at' => $status === ReviewStatus::Approved ? now() : null,
        ]);
    }

    /** @return array{0: int, 1: string} */
    private function aggregates(Doctor|Facility $target): array
    {
        $row = DB::table($target->getTable())->where('id', $target->id)->first(['reviews_count', 'rating_avg']);

        return [(int) $row->reviews_count, number_format((float) $row->rating_avg, 2)];
    }

    public function test_approve_reject_and_delete_keep_the_doctor_aggregates_exact(): void
    {
        $doctor = Doctor::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $five = $this->review($doctor, 5);
        $four = $this->review($doctor, 4);
        $this->assertSame([0, '0.00'], $this->aggregates($doctor), 'Pending reviews must not count.');

        $five->approve($moderator);
        $four->approve($moderator);
        $this->assertSame([2, '4.50'], $this->aggregates($doctor));

        $five->reject($moderator);
        $this->assertSame([1, '4.00'], $this->aggregates($doctor));

        $four->delete();
        $this->assertSame([0, '0.00'], $this->aggregates($doctor));
    }

    public function test_reviews_created_approved_and_rating_edits_are_counted(): void
    {
        $doctor = Doctor::factory()->create();

        $review = $this->review($doctor, 2, ReviewStatus::Approved);
        $this->assertSame([1, '2.00'], $this->aggregates($doctor));

        $review->update(['rating' => 5]);
        $this->assertSame([1, '5.00'], $this->aggregates($doctor));

        // A body-only edit changes nothing the aggregate reads.
        $review->update(['body' => 'Уредено.']);
        $this->assertSame([1, '5.00'], $this->aggregates($doctor));
    }

    public function test_moving_a_review_recomputes_both_targets(): void
    {
        $from = Doctor::factory()->create();
        $to = Doctor::factory()->create();
        $review = $this->review($from, 3, ReviewStatus::Approved);

        $review->update(['reviewable_id' => $to->id]);

        $this->assertSame([0, '0.00'], $this->aggregates($from));
        $this->assertSame([1, '3.00'], $this->aggregates($to));
    }

    public function test_pharmacy_reviews_update_the_facility_row(): void
    {
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy, 'slug' => 'apteka']);
        $this->review($pharmacy, 4, ReviewStatus::Approved);
        $this->review($pharmacy, 3, ReviewStatus::Approved);

        $this->assertSame([2, '3.50'], $this->aggregates($pharmacy));

        $this->getJson('/api/v1/pharmacies/apteka')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 2)
            ->assertJsonPath('data.review_summary.average_rating', 3.5);
    }

    /**
     * 29 reviews averaging 100/29 = 3.448… must still report 3.4. Storing the
     * average rounded to two decimals (3.45) would report 3.5.
     */
    public function test_stored_average_rounds_to_the_same_one_decimal_as_the_exact_average(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'edge']);
        $ratings = array_merge(array_fill(0, 13, 4), array_fill(0, 16, 3)); // 52 + 48 = 100

        foreach ($ratings as $rating) {
            $this->review($doctor, $rating, ReviewStatus::Approved);
        }

        $this->assertSame([29, '3.44'], $this->aggregates($doctor));

        $this->getJson('/api/v1/doctors/edge')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 29)
            ->assertJsonPath('data.review_summary.average_rating', 3.4);
    }

    public function test_bulk_backfill_matches_the_per_review_recompute(): void
    {
        $doctor = Doctor::factory()->create();
        $facility = Facility::factory()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Written without model events, as PerfSeeder and the migration see them.
        DB::table('reviews')->insert([
            ['user_id' => $user->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id, 'rating' => 5, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $other->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id, 'rating' => 2, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'reviewable_type' => Facility::class, 'reviewable_id' => $facility->id, 'rating' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ReviewAggregates::recomputeAll();

        $this->assertSame([2, '3.50'], $this->aggregates($doctor));
        $this->assertSame([0, '0.00'], $this->aggregates($facility));
    }

    public function test_truncated_average_formats_two_decimals(): void
    {
        $this->assertSame('0.00', ReviewAggregates::truncatedAverage(0, 0));
        $this->assertSame('3.44', ReviewAggregates::truncatedAverage(100, 29));
        $this->assertSame('4.66', ReviewAggregates::truncatedAverage(14, 3));
        $this->assertSame('5.00', ReviewAggregates::truncatedAverage(10, 2));
        $this->assertSame('1.05', ReviewAggregates::truncatedAverage(21, 20));
    }

    public function test_sort_by_rating_puts_unrated_doctors_last_and_breaks_ties_by_name(): void
    {
        $unrated = Doctor::factory()->create(['full_name' => 'д-р Ана Неоценета', 'slug' => 'unrated']);
        $low = Doctor::factory()->create(['full_name' => 'д-р Бојан Низок', 'slug' => 'low']);
        $highB = Doctor::factory()->create(['full_name' => 'д-р Весна Висок', 'slug' => 'high-b']);
        $highA = Doctor::factory()->create(['full_name' => 'д-р Анета Висок', 'slug' => 'high-a']);

        $this->review($low, 2, ReviewStatus::Approved);
        $this->review($highB, 5, ReviewStatus::Approved);
        $this->review($highA, 5, ReviewStatus::Approved);
        $this->review($unrated, 1); // pending: still unrated

        $slugs = collect($this->getJson('/api/v1/doctors?sort=rating')->assertOk()->json('data'))->pluck('slug')->all();

        $this->assertSame(['high-a', 'high-b', 'low', 'unrated'], $slugs);

        $this->getJson('/api/v1/doctors?sort=rating&min_reviews=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
