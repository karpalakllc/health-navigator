<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks in the N+1 fixes from the Part I audit (H3, H4). These assert a query
 * budget rather than an exact count, so ordinary refactors do not break them —
 * but re-introducing a per-model aggregate, or an uncached SiteSetting lookup,
 * will.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedDoctorsWithReviews(int $doctors, int $reviewsEach): void
    {
        $users = User::factory()->count($reviewsEach)->create();

        Doctor::factory()->count($doctors)->create(['is_published' => true])
            ->each(function (Doctor $doctor) use ($users): void {
                foreach ($users as $user) {
                    Review::query()->create([
                        'user_id' => $user->id,
                        'reviewable_type' => Doctor::class,
                        'reviewable_id' => $doctor->id,
                        'rating' => 4,
                        'status' => ReviewStatus::Approved,
                        'published_at' => now(),
                    ]);
                }
            });
    }

    /**
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $callback();

        return $queries;
    }

    public function test_doctor_listing_cost_does_not_grow_with_the_number_of_doctors(): void
    {
        // Warm the settings row, which is created lazily on the first request and
        // would otherwise make the two measurements incomparable.
        $this->getJson('/api/v1/settings/public')->assertOk();

        $this->seedDoctorsWithReviews(doctors: 3, reviewsEach: 2);
        $small = count($this->captureQueries(
            fn () => $this->getJson('/api/v1/doctors?per_page=25')->assertOk(),
        ));

        $this->seedDoctorsWithReviews(doctors: 9, reviewsEach: 2);
        $large = count($this->captureQueries(
            fn () => $this->getJson('/api/v1/doctors?per_page=25')->assertOk(),
        ));

        $this->assertSame(
            $small,
            $large,
            "Listing 12 doctors cost {$large} queries vs {$small} for 3 — the review "
            .'aggregate is being resolved per row again.',
        );
    }

    public function test_doctor_listing_stays_within_a_fixed_query_budget(): void
    {
        $this->seedDoctorsWithReviews(doctors: 10, reviewsEach: 3);

        $queries = $this->captureQueries(
            fn () => $this->getJson('/api/v1/doctors?per_page=25')->assertOk(),
        );

        $this->assertLessThanOrEqual(
            10,
            count($queries),
            "Doctor listing ran ".count($queries)." queries:\n".implode("\n", $queries),
        );
    }

    public function test_review_summary_values_survive_the_eager_loaded_path(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true, 'slug' => 'summary-check']);
        $users = User::factory()->count(3)->create();

        foreach ([5, 4, 3] as $index => $rating) {
            Review::query()->create([
                'user_id' => $users[$index]->id,
                'reviewable_type' => Doctor::class,
                'reviewable_id' => $doctor->id,
                'rating' => $rating,
                'status' => ReviewStatus::Approved,
                'published_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/doctors?per_page=25')
            ->assertOk()
            ->assertJsonPath('data.0.review_summary.count', 3)
            ->assertJsonPath('data.0.review_summary.average_rating', 4);

        $this->getJson('/api/v1/doctors/summary-check')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 3)
            ->assertJsonPath('data.review_summary.average_rating', 4);
    }

    public function test_facility_with_no_approved_reviews_reports_null_average(): void
    {
        Facility::factory()->create(['is_published' => true, 'slug' => 'empty-reviews']);

        $this->getJson('/api/v1/facilities/empty-reviews')
            ->assertOk()
            ->assertJsonPath('data.review_summary.count', 0)
            ->assertJsonPath('data.review_summary.average_rating', null);
    }

    public function test_settings_endpoint_does_not_requery_settings_per_middleware(): void
    {
        // Warm the row so creation is not counted.
        $this->getJson('/api/v1/settings/public')->assertOk();

        $queries = $this->captureQueries(
            fn () => $this->getJson('/api/v1/settings/public')->assertOk(),
        );

        $settingsQueries = array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'site_settings'),
        );

        $this->assertLessThanOrEqual(
            1,
            count($settingsQueries),
            'site_settings was queried '.count($settingsQueries).' times in one request.',
        );
    }
}
