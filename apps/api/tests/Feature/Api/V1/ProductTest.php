<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_products_with_from_price(): void
    {
        $pharmacy = Facility::factory()->create([
            'slug' => 'eurofarm',
            'type' => FacilityType::Pharmacy,
        ]);

        $cheap = Product::factory()->create(['slug' => 'cheap-product', 'name' => 'Cheap Product']);
        $expensive = Product::factory()->create(['slug' => 'expensive-product', 'name' => 'Expensive Product']);

        $pharmacy->products()->attach($cheap->id, [
            'price' => 100,
            'currency' => 'MKD',
            'is_available' => true,
            'price_updated_at' => now(),
        ]);
        $pharmacy->products()->attach($expensive->id, [
            'price' => 200,
            'currency' => 'MKD',
            'is_available' => true,
            'price_updated_at' => now(),
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/v1/products');
        $response->assertJsonPath('data.0.offer_count', 1);

        $response = $this->getJson('/api/v1/products?pharmacy=eurofarm');
        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?q=cheap')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cheap-product');
    }

    public function test_product_detail_includes_limited_offers(): void
    {
        $product = Product::factory()->create(['slug' => 'paracetamol', 'name' => 'Paracetamol']);

        foreach (range(1, 12) as $index) {
            $pharmacy = Facility::factory()->create([
                'slug' => 'pharmacy-'.$index,
                'type' => FacilityType::Pharmacy,
            ]);

            $pharmacy->products()->attach($product->id, [
                'price' => 100 + $index,
                'currency' => 'MKD',
                'is_available' => true,
                'price_updated_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/products/paracetamol')
            ->assertOk()
            ->assertJsonPath('data.offers_total', 12)
            ->assertJsonPath('data.offers_truncated', true)
            ->assertJsonCount(Product::MAX_EMBEDDED_OFFERS, 'data.offers');
    }

    public function test_unpublished_product_returns_404(): void
    {
        Product::factory()->unpublished()->create(['slug' => 'hidden-product']);

        $this->getJson('/api/v1/products/hidden-product')->assertNotFound();
    }
}
