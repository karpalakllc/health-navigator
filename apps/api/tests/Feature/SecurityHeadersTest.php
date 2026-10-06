<?php

namespace Tests\Feature;

use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Baseline security headers, asserted per route group: the JSON API, the
 * Filament admin panel, and error responses (which never reach route
 * middleware, so a per-group registration would miss them).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Outside local/testing, TrustHosts pins Symfony's process-wide trusted
        // host patterns to APP_URL; left set, they reject other tests' hosts.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    private function assertBaseline(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', SetSecurityHeaders::PERMISSIONS_POLICY)
            ->assertHeader('X-Frame-Options', 'DENY')
            // Media is embedded cross-origin by the web app; never restrict it here.
            ->assertHeaderMissing('Cross-Origin-Resource-Policy');
    }

    public function test_api_responses_carry_a_deny_everything_policy(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk();

        $this->assertBaseline($response);
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_api_errors_carry_them_too(): void
    {
        $response = $this->getJson('/api/v1/no-such-route')->assertNotFound();

        $this->assertBaseline($response);
        $response->assertHeader('Content-Security-Policy', SetSecurityHeaders::API_CSP);

        $this->assertBaseline($this->getJson('/api/v1/me')->assertUnauthorized());
    }

    public function test_the_admin_panel_enforces_framing_and_reports_the_fetch_policy(): void
    {
        $response = $this->get('/admin/login')->assertOk();

        $this->assertBaseline($response);
        $response->assertHeader('Content-Security-Policy', SetSecurityHeaders::ADMIN_CSP)
            ->assertHeader('Content-Security-Policy-Report-Only', SetSecurityHeaders::ADMIN_CSP_REPORT_ONLY);

        $enforced = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $enforced);
        // Filament/Livewire/Alpine need inline and eval'd script; an enforced
        // script-src would blank the panel, so it must stay report-only.
        $this->assertStringNotContainsString('script-src', $enforced);
    }

    public function test_admin_redirects_carry_them(): void
    {
        $this->assertBaseline($this->get('/admin')->assertRedirect());
    }

    public function test_hsts_is_sent_only_over_https_in_a_deployment(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Not deployed: never, even over TLS.
        $this->get("https://{$host}/api/v1/health")->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';

        $this->get("http://{$host}/api/v1/health")->assertHeaderMissing('Strict-Transport-Security');
        $this->get("https://{$host}/api/v1/health")
            ->assertHeader('Strict-Transport-Security', SetSecurityHeaders::HSTS);
    }

    public function test_a_route_can_set_its_own_policy(): void
    {
        Route::get('/__own-policy', fn () => response('ok')
            ->header('Content-Security-Policy', "default-src 'self'")
            ->header('X-Frame-Options', 'SAMEORIGIN'));

        $this->get('/__own-policy')
            ->assertHeader('Content-Security-Policy', "default-src 'self'")
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
