<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_cities_with_published_profiles_merged_across_scripts(): void
    {
        Doctor::factory()->count(2)->create(['is_published' => true, 'city' => 'Скопје']);
        Doctor::factory()->create(['is_published' => true, 'city' => ' Skopje ']);
        Doctor::factory()->create(['is_published' => true, 'city' => 'Битола']);
        Doctor::factory()->unpublished()->count(3)->create(['city' => 'Охрид']);
        Doctor::factory()->create(['is_published' => true, 'city' => null]);
        Facility::factory()->create(['is_published' => true, 'city' => 'Скопје', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['is_published' => true, 'city' => 'Струга', 'type' => FacilityType::Hospital]);

        $this->getJson('/api/v1/locations/cities')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['name' => 'Скопје', 'doctors_count' => 3, 'facilities_count' => 1],
                ['name' => 'Битола', 'doctors_count' => 1, 'facilities_count' => 0],
                ['name' => 'Струга', 'doctors_count' => 0, 'facilities_count' => 1],
            ]]);
    }

    public function test_pharmacy_cities_count_only_while_the_module_is_on(): void
    {
        Facility::factory()->create(['is_published' => true, 'city' => 'Кочани', 'type' => FacilityType::Pharmacy]);

        $this->getJson('/api/v1/locations/cities')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Кочани')
            ->assertJsonPath('data.0.facilities_count', 1);

        SiteSetting::current()->update(['public_pharmacies' => false]);

        $this->getJson('/api/v1/locations/cities')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
