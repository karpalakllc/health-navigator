<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
