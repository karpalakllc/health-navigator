<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_returns_503_when_disabled(): void
    {
        SiteSetting::current()->update(['public_products' => false]);

        $this->getJson('/api/v1/products')
            ->assertStatus(503);
    }

    public function test_pharmacies_endpoint_returns_503_when_disabled(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => false]);

        $this->getJson('/api/v1/pharmacies')
            ->assertStatus(503);
    }

    public function test_pharmacy_review_submission_returns_503_when_disabled(): void
    {
        Facility::factory()->create([
            'slug' => 'eurofarm',
            'type' => FacilityType::Pharmacy,
        ]);
        SiteSetting::current()->update(['public_pharmacies' => false]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/pharmacies/eurofarm/reviews', [
            'rating' => 5,
            'body' => 'Helpful staff and clear pricing boards.',
        ])->assertStatus(503);

        $this->assertSame(0, Review::query()->count());
    }
}
