<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_endpoint_returns_flags(): void
    {
        SiteSetting::current()->update([
            'public_products' => false,
            'public_pharmacies' => false,
            'registrations_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk()
            ->assertJsonPath('data.public_products', false)
            ->assertJsonPath('data.public_pharmacies', false)
            ->assertJsonPath('data.registrations_enabled', true);
    }
}
