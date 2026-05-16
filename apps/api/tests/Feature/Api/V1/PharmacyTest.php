<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_pharmacies_only(): void
    {
        Facility::factory()->create([
            'slug' => 'eurofarm',
            'type' => FacilityType::Pharmacy,
        ]);
        Facility::factory()->create([
            'slug' => 'clinic-a',
            'type' => FacilityType::Clinic,
        ]);
        Facility::factory()->unpublished()->create([
            'slug' => 'hidden-pharmacy',
            'type' => FacilityType::Pharmacy,
        ]);

        $this->getJson('/api/v1/pharmacies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'eurofarm');
    }

    public function test_facilities_index_excludes_pharmacies(): void
    {
        Facility::factory()->create(['slug' => 'clinic-a', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['slug' => 'pharmacy-a', 'type' => FacilityType::Pharmacy]);

        $this->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'clinic-a');
    }

    public function test_pharmacy_detail_and_shelf_products(): void
    {
        $pharmacy = Facility::factory()->create([
            'slug' => 'eurofarm',
            'type' => FacilityType::Pharmacy,
        ]);

        $product = Product::factory()->create(['slug' => 'paracetamol', 'name' => 'Paracetamol']);

        $pharmacy->products()->attach($product->id, [
            'price' => 120,
            'currency' => 'MKD',
            'is_available' => true,
            'price_updated_at' => now(),
        ]);

        $this->getJson('/api/v1/pharmacies/eurofarm')
            ->assertOk()
            ->assertJsonPath('data.slug', 'eurofarm')
            ->assertJsonStructure(['data' => ['review_summary', 'latitude', 'longitude']]);

        $this->getJson('/api/v1/pharmacies/eurofarm/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'paracetamol')
            ->assertJsonPath('data.0.price', 120);
    }

    public function test_pharmacy_detail_includes_coordinates_when_set(): void
    {
        Facility::factory()->create([
            'slug' => 'mapped-pharmacy',
            'type' => FacilityType::Pharmacy,
            'latitude' => 41.9965,
            'longitude' => 21.4314,
        ]);

        $this->getJson('/api/v1/pharmacies/mapped-pharmacy')
            ->assertOk()
            ->assertJsonPath('data.latitude', 41.9965)
            ->assertJsonPath('data.longitude', 21.4314);
    }

    public function test_clinical_facility_slug_returns_404_on_pharmacy_routes(): void
    {
        Facility::factory()->create([
            'slug' => 'klinika-a',
            'type' => FacilityType::Clinic,
        ]);

        $this->getJson('/api/v1/pharmacies/klinika-a')->assertNotFound();
    }
}
