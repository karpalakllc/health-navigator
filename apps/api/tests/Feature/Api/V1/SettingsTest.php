<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

        SiteSetting::current()->update([
            'footer_emergency_text' => 'Custom emergency text.',
            'copyright_name' => 'Test Co',
        ]);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk()
            ->assertJsonPath('data.public_products', false)
            ->assertJsonPath('data.public_pharmacies', false)
            ->assertJsonPath('data.registrations_enabled', true)
            ->assertJsonPath('data.footer_emergency_text', 'Custom emergency text.')
            ->assertJsonPath('data.copyright_name', 'Test Co');
    }

    /**
     * Octane and queue workers keep one container alive across requests and
     * only call forgetScopedInstances() between them. An instance() memo
     * survived that and served the first request's settings until restart.
     */
    public function test_the_memo_is_reread_after_the_request_scope_ends(): void
    {
        SiteSetting::current()->update(['copyright_name' => 'Before']);
        $this->assertSame('Before', SiteSetting::current()->copyright_name);

        // A change made elsewhere (another worker, the admin) after the shared
        // cache entry expired: no model event reaches this process.
        SiteSetting::query()->update(['copyright_name' => 'After']);
        Cache::forget(SiteSetting::CACHE_KEY);

        $this->assertSame(
            'Before',
            SiteSetting::current()->copyright_name,
            'Within one request the memo should still be served.',
        );

        $this->app->forgetScopedInstances();

        $this->assertSame('After', SiteSetting::current()->copyright_name);
    }
}
