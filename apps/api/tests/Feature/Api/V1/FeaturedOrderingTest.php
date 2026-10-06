<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeMeilisearchEngine;
use Tests\TestCase;

/**
 * Featured profiles lead every directory list — within whatever filters the
 * visitor chose — and the chosen sort still orders each group. The flag is
 * returned on every item, so a highlighted card can always be labelled.
 * Names are Latin so the expected order does not depend on a collation.
 */
class FeaturedOrderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function doctor(string $name, bool $featured, array $attributes = []): Doctor
    {
        return Doctor::factory()->create(array_merge([
            'slug' => str_replace(' ', '-', strtolower($name)),
            'full_name' => $name,
            'city' => 'Skopje',
            'is_featured' => $featured,
        ], $attributes));
    }

    /**
     * @return list<string>
     */
    private function slugs(string $uri, string $path = 'data'): array
    {
        return array_column($this->getJson($uri)->assertOk()->json($path), 'slug');
    }

    public function test_doctors_sorted_by_name_list_featured_first(): void
    {
        $this->doctor('Alpha', false);
        $this->doctor('Bravo', false);
        $this->doctor('Charlie', true);
        $this->doctor('Delta', true);

        $this->assertSame(['charlie', 'delta', 'alpha', 'bravo'], $this->slugs('/api/v1/doctors'));
        $this->assertSame(['charlie', 'delta', 'alpha', 'bravo'], $this->slugs('/api/v1/doctors?sort=name'));
    }

    public function test_doctors_sorted_by_rating_list_featured_first_then_by_rating(): void
    {
        $this->doctor('Alpha', true)->forceFill(['rating_avg' => 3.0, 'reviews_count' => 2])->save();
        $this->doctor('Bravo', false)->forceFill(['rating_avg' => 5.0, 'reviews_count' => 2])->save();
        $this->doctor('Charlie', true)->forceFill(['rating_avg' => 4.0, 'reviews_count' => 2])->save();
        $this->doctor('Delta', false)->forceFill(['rating_avg' => 2.0, 'reviews_count' => 2])->save();

        $this->assertSame(
            ['charlie', 'alpha', 'bravo', 'delta'],
            $this->slugs('/api/v1/doctors?sort=rating'),
        );
    }

    public function test_featured_doctors_lead_within_filters_and_pagination_totals_hold(): void
    {
        $cardiology = Specialty::factory()->create(['slug' => 'cardiology']);

        foreach ([['Alpha', false], ['Bravo', false], ['Charlie', true], ['Delta', true]] as [$name, $featured]) {
            $this->doctor($name, $featured)->specialties()->attach($cardiology->id, ['is_primary' => true]);
        }

        // Featured, but outside the filter: must not be pulled in.
        $this->doctor('Echo', true, ['city' => 'Bitola']);

        $first = $this->getJson('/api/v1/doctors?specialty=cardiology&city=Skopje&per_page=3')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertSame(['charlie', 'delta', 'alpha'], array_column($first->json('data'), 'slug'));
        $this->assertSame(
            ['bravo'],
            $this->slugs('/api/v1/doctors?specialty=cardiology&city=Skopje&per_page=3&page=2'),
        );
    }

    public function test_doctor_order_is_unchanged_when_nobody_is_featured(): void
    {
        $this->doctor('Delta', false);
        $this->doctor('Alpha', false);
        $this->doctor('Charlie', false);
        $this->doctor('Bravo', false);

        $this->assertSame(['alpha', 'bravo', 'charlie', 'delta'], $this->slugs('/api/v1/doctors'));
    }

    public function test_doctor_list_items_label_featured_and_sponsored(): void
    {
        $this->doctor('Alpha', true);
        $this->doctor('Bravo', false, ['is_sponsored' => true]);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonPath('data.0.is_sponsored', false)
            ->assertJsonPath('data.1.is_featured', false)
            ->assertJsonPath('data.1.is_sponsored', true);
    }

    public function test_facilities_list_featured_first_within_filters(): void
    {
        foreach ([['Alpha', false], ['Bravo', true], ['Charlie', false], ['Delta', true]] as [$name, $featured]) {
            Facility::factory()->create([
                'slug' => strtolower($name),
                'name' => $name,
                'type' => FacilityType::Clinic,
                'city' => 'Skopje',
                'is_featured' => $featured,
            ]);
        }

        Facility::factory()->create([
            'slug' => 'echo', 'name' => 'Echo', 'type' => FacilityType::Hospital, 'is_featured' => true,
        ]);

        $this->assertSame(
            ['bravo', 'delta', 'alpha', 'charlie'],
            $this->slugs('/api/v1/facilities?type=clinic'),
        );

        $this->getJson('/api/v1/facilities?type=clinic&per_page=3')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonPath('data.2.is_featured', false);
    }

    public function test_pharmacies_list_featured_first_and_expose_the_flag(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => true]);

        foreach ([['Alpha', false], ['Bravo', false], ['Charlie', true]] as [$name, $featured]) {
            Facility::factory()->pharmacy()->create([
                'slug' => strtolower($name),
                'name' => $name,
                'is_featured' => $featured,
            ]);
        }

        $this->assertSame(['charlie', 'alpha', 'bravo'], $this->slugs('/api/v1/pharmacies'));

        $this->getJson('/api/v1/pharmacies')
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonPath('data.1.is_featured', false);
        $this->getJson('/api/v1/pharmacies/charlie')->assertJsonPath('data.is_featured', true);
        $this->getJson('/api/v1/facilities/'.Facility::factory()->create(['is_featured' => true])->slug)
            ->assertJsonPath('data.is_featured', true);
    }

    public function test_sql_unified_search_lists_featured_first_in_each_vertical(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => true]);

        $this->doctor('Alpha Med', false);
        $this->doctor('Bravo Med', true);
        Facility::factory()->create(['slug' => 'alpha-clinic', 'name' => 'Alpha Med Clinic', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['slug' => 'bravo-clinic', 'name' => 'Bravo Med Clinic', 'type' => FacilityType::Clinic, 'is_featured' => true]);
        Facility::factory()->pharmacy()->create(['slug' => 'alpha-pharmacy', 'name' => 'Alpha Med Pharmacy']);
        Facility::factory()->pharmacy()->create(['slug' => 'bravo-pharmacy', 'name' => 'Bravo Med Pharmacy', 'is_featured' => true]);

        $this->assertSame(['bravo-med', 'alpha-med'], $this->slugs('/api/v1/search?q=Med', 'data.doctors.data'));
        $this->assertSame(['bravo-clinic', 'alpha-clinic'], $this->slugs('/api/v1/search?q=Med', 'data.facilities.data'));
        $this->assertSame(['bravo-pharmacy', 'alpha-pharmacy'], $this->slugs('/api/v1/search?q=Med', 'data.pharmacies.data'));
    }

    public function test_meilisearch_unified_search_breaks_ties_with_the_featured_flag(): void
    {
        $engine = FakeMeilisearchEngine::install();

        $this->doctor('Alpha Med', false);
        $this->doctor('Bravo Med', true);
        Facility::factory()->create(['slug' => 'alpha-clinic', 'name' => 'Alpha Med Clinic', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['slug' => 'bravo-clinic', 'name' => 'Bravo Med Clinic', 'type' => FacilityType::Clinic, 'is_featured' => true]);

        $this->assertSame(['bravo-med', 'alpha-med'], $this->slugs('/api/v1/search?q=Med', 'data.doctors.data'));
        $this->assertSame(['bravo-clinic', 'alpha-clinic'], $this->slugs('/api/v1/search?q=Med', 'data.facilities.data'));

        $this->assertSame(['is_featured:desc'], $engine->lastSearch(config('scout.prefix').'doctors')['params']['sort'] ?? null);
        $this->assertSame(['is_featured:desc'], $engine->lastSearch(config('scout.prefix').'facilities')['params']['sort'] ?? null);
    }

    /**
     * Rows that share a name come back in id order, on one page or across
     * pages. They are inserted in the reverse of their ids, so a database
     * that returns ties in storage or index order (Postgres does) would list
     * them differently without the id key.
     *
     * @var array<string, int>
     */
    private const TWINS = ['twin-c' => 300, 'twin-b' => 200, 'twin-a' => 100];

    /**
     * @return list<string>
     */
    private function pagedSlugs(string $uri): array
    {
        $slugs = [];
        foreach (range(1, count(self::TWINS)) as $page) {
            $slugs = [...$slugs, ...$this->slugs($uri.(str_contains($uri, '?') ? '&' : '?').'per_page=1&page='.$page)];
        }

        return $slugs;
    }

    public function test_doctors_with_the_same_name_page_in_a_stable_order(): void
    {
        foreach (self::TWINS as $slug => $id) {
            $this->doctor('Same Name', false, ['id' => $id, 'slug' => $slug]);
        }

        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->slugs('/api/v1/doctors?sort=name'));
        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->pagedSlugs('/api/v1/doctors?sort=name'));
        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->slugs('/api/v1/doctors?sort=rating'));
    }

    public function test_facilities_with_the_same_name_page_in_a_stable_order(): void
    {
        foreach (self::TWINS as $slug => $id) {
            Facility::factory()->create([
                'id' => $id, 'slug' => $slug, 'name' => 'Same Name', 'type' => FacilityType::Clinic,
            ]);
        }

        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->slugs('/api/v1/facilities?type=clinic'));
        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->pagedSlugs('/api/v1/facilities?type=clinic'));
    }

    public function test_pharmacies_with_the_same_name_page_in_a_stable_order(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => true]);

        foreach (self::TWINS as $slug => $id) {
            Facility::factory()->pharmacy()->create(['id' => $id, 'slug' => $slug, 'name' => 'Same Name']);
        }

        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->slugs('/api/v1/pharmacies'));
        $this->assertSame(['twin-a', 'twin-b', 'twin-c'], $this->pagedSlugs('/api/v1/pharmacies'));
    }
}
