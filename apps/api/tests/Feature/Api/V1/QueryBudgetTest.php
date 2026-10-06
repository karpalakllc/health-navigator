<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Language;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeMeilisearchEngine;
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
                $doctor->facilities()->attach(
                    Facility::factory()->create(['is_published' => true])->id,
                    ['is_primary' => true],
                );

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
        $this->getJson('/api/v1/settings/public')->assertOk();
        $this->seedDoctorsWithReviews(doctors: 10, reviewsEach: 3);

        foreach (['/api/v1/doctors?per_page=25', '/api/v1/doctors?per_page=25&sort=rating&min_reviews=1'] as $uri) {
            $queries = $this->captureQueries(fn () => $this->getJson($uri)->assertOk());

            // settings, count, page, specialties, facilities.
            $this->assertLessThanOrEqual(
                5,
                count($queries),
                "{$uri} ran ".count($queries)." queries:\n".implode("\n", $queries),
            );
        }
    }

    /**
     * phone and office_hours are columns of the rows already loaded; the
     * language filter is a whereHas inside the count and page queries plus one
     * exists check in validation.
     */
    public function test_contact_fields_and_language_filter_stay_within_the_budget(): void
    {
        $this->getJson('/api/v1/settings/public')->assertOk();
        $this->seedDoctorsWithReviews(doctors: 10, reviewsEach: 1);
        $language = Language::factory()->create(['slug' => 'angliski']);
        Doctor::query()->each(fn (Doctor $doctor) => $doctor->languages()->attach($language->id));
        Facility::query()->update(['type' => FacilityType::Clinic]);
        Facility::factory()->pharmacy()->count(5)->create();
        Doctor::query()->update(['phone' => '+389 2 123 456', 'office_hours' => json_encode(['Пон–Пет' => '08:00–16:00'])]);
        Facility::query()->update(['phone' => '+389 2 123 456', 'office_hours' => json_encode(['Пон–Пет' => '08:00–16:00'])]);

        foreach ([
            // settings, count, page, specialties, facilities.
            '/api/v1/doctors?per_page=25' => 5,
            // + the validation exists check.
            '/api/v1/doctors?per_page=25&language=angliski' => 6,
            // settings, count, page (departments_count is a subquery).
            '/api/v1/facilities?per_page=25' => 3,
            '/api/v1/pharmacies?per_page=25' => 3,
        ] as $uri => $budget) {
            $queries = $this->captureQueries(fn () => $this->getJson($uri)->assertOk()
                ->assertJsonPath('data.0.phone', '+389 2 123 456'));

            $this->assertLessThanOrEqual(
                $budget,
                count($queries),
                "{$uri} ran ".count($queries)." queries:\n".implode("\n", $queries),
            );
        }
    }

    /**
     * review_summary, sort=rating and min_reviews read the denormalised
     * reviews_count / rating_avg columns. Correlated COUNT/AVG subqueries
     * against reviews ran for every published doctor before LIMIT applied.
     */
    public function test_directory_listings_do_not_query_the_reviews_table(): void
    {
        $this->seedDoctorsWithReviews(doctors: 3, reviewsEach: 2);

        foreach ([
            '/api/v1/doctors?sort=rating&min_reviews=1',
            '/api/v1/facilities',
            '/api/v1/pharmacies',
            '/api/v1/search?q='.rawurlencode('д-р'),
        ] as $uri) {
            $queries = $this->captureQueries(fn () => $this->getJson($uri)->assertOk());

            $this->assertSame(
                [],
                array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, '"reviews"'))),
                "{$uri} still aggregates reviews per row.",
            );
        }
    }

    public function test_review_summary_values_survive_the_denormalised_path(): void
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

    /**
     * Every factory doctor is named "д-р …", so q=д-р hits all of them.
     */
    private function assertSearchCostIsFlat(): void
    {
        $this->getJson('/api/v1/settings/public')->assertOk();

        $this->seedDoctorsWithReviews(doctors: 2, reviewsEach: 2);
        $small = $this->captureQueries(
            fn () => $this->getJson('/api/v1/search?per_page=10&q='.rawurlencode('д-р'))
                ->assertOk()
                ->assertJsonCount(2, 'data.doctors.data'),
        );

        $this->seedDoctorsWithReviews(doctors: 6, reviewsEach: 2);
        $large = $this->captureQueries(
            fn () => $this->getJson('/api/v1/search?per_page=10&q='.rawurlencode('д-р'))
                ->assertOk()
                ->assertJsonCount(8, 'data.doctors.data'),
        );

        $this->assertSame(
            count($small),
            count($large),
            'Searching 8 doctors cost '.count($large).' queries vs '.count($small)
            ." for 2 — something is resolved per row:\n".implode("\n", $large),
        );
    }

    public function test_sql_unified_search_cost_does_not_grow_with_the_number_of_results(): void
    {
        $this->assertSearchCostIsFlat();
    }

    public function test_meilisearch_unified_search_cost_does_not_grow_with_the_number_of_results(): void
    {
        FakeMeilisearchEngine::install();

        $this->assertSearchCostIsFlat();
    }
}
