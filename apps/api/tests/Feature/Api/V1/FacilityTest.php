<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_facilities_with_pagination_meta(): void
    {
        Facility::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/facilities?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name', 'type', 'city', 'avatar_url', 'review_summary'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_excludes_unpublished_facilities(): void
    {
        Facility::factory()->create(['slug' => 'published-facility']);
        Facility::factory()->unpublished()->create(['slug' => 'hidden-facility']);

        $this->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-facility');
    }

    public function test_filters_by_type(): void
    {
        Facility::factory()->create([
            'slug' => 'clinic-a',
            'type' => FacilityType::Clinic,
        ]);
        Facility::factory()->create([
            'slug' => 'hospital-a',
            'type' => FacilityType::Hospital,
        ]);

        $this->getJson('/api/v1/facilities?type=clinic')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'clinic-a');
    }

    public function test_filters_by_city_case_insensitively(): void
    {
        Facility::factory()->create(['slug' => 'skopje-facility', 'city' => 'Skopje']);
        Facility::factory()->create(['slug' => 'bitola-facility', 'city' => 'Bitola']);

        $this->getJson('/api/v1/facilities?city=SKOPJE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'skopje-facility');
    }

    public function test_filters_by_name_query(): void
    {
        Facility::factory()->create(['slug' => 'ana-clinic', 'name' => 'Ana Clinic']);
        Facility::factory()->create(['slug' => 'other-clinic', 'name' => 'Other Clinic']);

        $this->getJson('/api/v1/facilities?q=ana')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'ana-clinic');
    }

    public function test_shows_published_facility_detail_with_doctors(): void
    {
        $facility = Facility::factory()->create([
            'slug' => 'klinika-ana',
            'name' => 'Клиника Ана',
            'type' => FacilityType::Clinic,
        ]);

        $doctor = Doctor::factory()->create([
            'slug' => 'ana-petrovska',
            'full_name' => 'д-р Ана Петровска',
        ]);
        $facility->doctors()->attach($doctor->id, ['is_primary' => true]);

        $this->getJson('/api/v1/facilities/klinika-ana')
            ->assertOk()
            ->assertJsonPath('data.slug', 'klinika-ana')
            ->assertJsonPath('data.type', 'clinic')
            ->assertJsonPath('data.doctors.0.slug', 'ana-petrovska')
            ->assertJsonPath('data.doctors.0.is_primary', true);
    }

    public function test_detail_excludes_unpublished_doctors(): void
    {
        $facility = Facility::factory()->create(['slug' => 'test-facility']);

        $published = Doctor::factory()->create(['slug' => 'published-doc']);
        $unpublished = Doctor::factory()->unpublished()->create(['slug' => 'hidden-doc']);

        $facility->doctors()->attach($published->id, ['is_primary' => true]);
        $facility->doctors()->attach($unpublished->id, ['is_primary' => false]);

        $this->getJson('/api/v1/facilities/test-facility')
            ->assertOk()
            ->assertJsonCount(1, 'data.doctors')
            ->assertJsonPath('data.doctors.0.slug', 'published-doc');
    }

    public function test_detail_returns_404_for_unpublished_facility(): void
    {
        Facility::factory()->unpublished()->create(['slug' => 'hidden-facility']);

        $this->getJson('/api/v1/facilities/hidden-facility')->assertNotFound();
    }

    public function test_validates_list_query_parameters(): void
    {
        $this->getJson('/api/v1/facilities?type=pharmacy')->assertUnprocessable();
        $this->getJson('/api/v1/facilities?per_page=100')->assertUnprocessable();
    }
}
