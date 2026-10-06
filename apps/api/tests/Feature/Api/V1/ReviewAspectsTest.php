<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\ReviewAspectRating;
use App\Models\User;
use App\Support\ReviewAggregates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Optional aspect sub-ratings: stored with the review, averaged per profile
 * over approved reviews only (withheld below three ratings), plus the
 * twelve-month rating trend.
 */
class ReviewAspectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->forgetRateLimits();
        Mail::fake();
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Member');

        return $user;
    }

    /**
     * @param  array<string, int>  $aspects
     */
    private function approvedReviewWithAspects(Doctor $doctor, int $rating, array $aspects, array $attributes = []): Review
    {
        $review = Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'rating' => $rating,
            ...$attributes,
        ]);

        foreach ($aspects as $aspect => $value) {
            $review->aspectRatings()->create(['aspect' => $aspect, 'rating' => $value]);
        }

        $review->approve(User::factory()->create());

        if (isset($attributes['published_at'])) {
            DB::table('reviews')->where('id', $review->id)->update(['published_at' => $attributes['published_at']]);
        }

        return $review;
    }

    public function test_a_member_submits_optional_aspect_ratings_with_the_review(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        Sanctum::actingAs($this->member());

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', [
            'rating' => 4,
            'aspects' => ['communication' => 5, 'waiting_time' => 2, 'respect' => null],
        ])
            ->assertCreated()
            ->assertJsonPath('data.aspects', ['communication' => 5, 'waiting_time' => 2]);

        $review = Review::query()->sole();
        $this->assertSame(
            ['communication' => 5, 'waiting_time' => 2],
            $review->aspectRatings()->orderBy('aspect')->get()->mapWithKeys(fn (ReviewAspectRating $a) => [$a->aspect->value => $a->rating])->all(),
        );
    }

    public function test_aspects_stay_optional_and_overall_stars_stay_required(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        Sanctum::actingAs($this->member());

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['aspects' => ['communication' => 5]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 3])->assertCreated();
        $this->assertSame(0, ReviewAspectRating::query()->count());
    }

    public function test_aspects_must_belong_to_the_profile_type_and_be_one_to_five(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        Facility::factory()->create(['slug' => 'klinika-ana', 'is_published' => true]);
        Sanctum::actingAs($this->member());

        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 4, 'aspects' => ['cleanliness' => 4]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aspects');
        $this->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 4, 'aspects' => ['communication' => 6]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aspects.communication');
        $this->assertSame(0, Review::query()->count(), 'a refused aspect refuses the whole review');

        $this->postJson('/api/v1/facilities/klinika-ana/reviews', ['rating' => 4, 'aspects' => ['cleanliness' => 4, 'staff' => 5]])
            ->assertCreated();
    }

    public function test_profile_aspect_averages_count_approved_reviews_and_hide_below_three(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        $this->approvedReviewWithAspects($doctor, 5, ['communication' => 5, 'waiting_time' => 1]);
        $this->approvedReviewWithAspects($doctor, 4, ['communication' => 4, 'waiting_time' => 2]);
        $this->approvedReviewWithAspects($doctor, 4, ['communication' => 4]);
        // Pending and removed reviews never count.
        $pending = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $pending->aspectRatings()->create(['aspect' => 'communication', 'rating' => 1]);
        $removed = $this->approvedReviewWithAspects($doctor, 1, ['communication' => 1, 'waiting_time' => 1]);
        $removed->reject(User::factory()->create(), 'note', afterReport: true);

        $stored = json_decode((string) DB::table('doctors')->where('id', $doctor->id)->value('aspect_ratings'), true);
        $this->assertSame(['count' => 3, 'average' => 4.33], $stored['communication']);
        $this->assertSame(2, $stored['waiting_time']['count']);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.aspects', [
                ['key' => 'communication', 'count' => 3, 'average' => 4.3],
                ['key' => 'explanation', 'count' => 0, 'average' => null],
                ['key' => 'waiting_time', 'count' => 2, 'average' => null],
                ['key' => 'respect', 'count' => 0, 'average' => null],
            ]);
    }

    public function test_recompute_all_rebuilds_aspect_aggregates(): void
    {
        $doctor = Doctor::factory()->create();
        $this->approvedReviewWithAspects($doctor, 5, ['respect' => 5]);
        DB::table('doctors')->update(['aspect_ratings' => null]);

        ReviewAggregates::recomputeAll();

        $stored = json_decode((string) DB::table('doctors')->where('id', $doctor->id)->value('aspect_ratings'), true);
        $this->assertSame(['respect' => ['count' => 1, 'average' => 5]], $stored);
    }

    public function test_the_trend_averages_three_month_periods_and_hides_below_five_reviews(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);

        // Periods: Nov 2025–Jan 2026, Feb–Apr, May–Jul, Aug–Oct 2026.
        $this->approvedReviewWithAspects($doctor, 2, [], ['published_at' => '2025-11-03 09:00:00']);
        $this->approvedReviewWithAspects($doctor, 4, [], ['published_at' => '2026-01-31 23:00:00']);
        $this->approvedReviewWithAspects($doctor, 5, [], ['published_at' => '2026-08-01 00:00:00']);
        $this->approvedReviewWithAspects($doctor, 4, [], ['published_at' => '2026-10-10 10:00:00']);
        // Older than twelve months: outside the window.
        $this->approvedReviewWithAspects($doctor, 1, [], ['published_at' => '2025-10-31 23:59:59']);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.trend', null);

        $this->approvedReviewWithAspects($doctor, 3, [], ['published_at' => '2026-09-15 10:00:00']);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.trend', [
                ['start' => '2025-11-01', 'end' => '2026-01-31', 'count' => 2, 'average' => 3],
                ['start' => '2026-02-01', 'end' => '2026-04-30', 'count' => 0, 'average' => null],
                ['start' => '2026-05-01', 'end' => '2026-07-31', 'count' => 0, 'average' => null],
                ['start' => '2026-08-01', 'end' => '2026-10-31', 'count' => 3, 'average' => 4],
            ]);
    }

    public function test_the_list_payload_carries_each_reviews_aspects(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'is_published' => true]);
        $this->approvedReviewWithAspects($doctor, 5, ['explanation' => 5]);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.aspects', ['explanation' => 5]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->sole()->status);
    }
}
