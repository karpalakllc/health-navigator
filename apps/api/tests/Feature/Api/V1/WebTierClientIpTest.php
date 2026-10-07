<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The web tier vouches for its visitor's address with a shared secret.
 *
 * Every server-side render and browser write reaches the API from the web
 * tier's egress address. Unless the API can learn the visitor behind it, every
 * IP-keyed limiter meters the whole site as one client — and the only other way
 * to learn it, trusting X-Forwarded-For from "*", lets any caller choose its own
 * bucket. See TrustWebTierClientIp.
 */
class WebTierClientIpTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-web-tier-secret-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRateLimits();

        config([
            'zdravje.web_tier.secret' => self::SECRET,
            // Nothing trusted: whatever $request->ip() returns beyond the socket
            // address has to come from the web-tier middleware.
            'trustedproxy.proxies' => null,
        ]);

        // Echoes what downstream code sees once the global stack has run.
        Route::get('/__test/client-ip', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'auth_header' => $request->headers->get('X-Web-Tier-Auth'),
            'client_ip_header' => $request->headers->get('X-Client-IP'),
            // The server bag keeps its own copy of every header.
            'auth_server' => $request->server('HTTP_X_WEB_TIER_AUTH'),
            'client_ip_server' => $request->server('HTTP_X_CLIENT_IP'),
        ]);
    }

    /** @param array<string, string> $headers */
    private function probe(array $headers): TestResponse
    {
        return $this->withHeaders($headers)->getJson('/__test/client-ip')->assertOk();
    }

    private function login(string $email, array $headers): TestResponse
    {
        return $this->withHeaders($headers)
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password-here']);
    }

    public function test_a_valid_secret_makes_the_forwarded_address_the_client_ip(): void
    {
        $this->probe([
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '198.51.100.7']);
    }

    public function test_the_secret_and_the_address_header_never_reach_downstream_code(): void
    {
        // Anything that records request headers (exception reporters, logs) runs
        // after the middleware, so it must not find the secret there.
        $this->probe([
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['auth_header' => null, 'client_ip_header' => null, 'auth_server' => null, 'client_ip_server' => null]);

        $this->probe([
            'X-Web-Tier-Auth' => 'wrong',
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['auth_header' => null, 'client_ip_header' => null, 'auth_server' => null, 'client_ip_server' => null]);
    }

    public function test_the_forwarded_address_is_what_the_throttle_keys_on(): void
    {
        $this->withHeaders([
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->getJson('/api/v1/health')->assertOk();

        // ThrottleRequests keys a named limiter as md5(name.limit-key).
        $this->assertSame(1, RateLimiter::attempts(md5('api'.'ip:198.51.100.7')));
        $this->assertSame(0, RateLimiter::attempts(md5('api'.'ip:127.0.0.1')));
    }

    public function test_two_visitors_behind_the_web_tier_do_not_share_a_login_bucket(): void
    {
        // Each unknown-address login pays a deliberate cost-12 bcrypt check (the
        // timing equaliser), so 45 of them can outlast the one-minute window on
        // a slow run and reset it mid-loop. Freeze the clock: the test is about
        // whose bucket a request lands in, not about the window expiring.
        $this->freezeTime();

        $asVisitor = fn (string $ip) => ['X-Web-Tier-Auth' => self::SECRET, 'X-Client-IP' => $ip];

        // api-login allows 40/min per address. Exhaust it for one visitor…
        $statuses = [];
        foreach (range(1, 45) as $i) {
            $statuses[] = $this->login("probe{$i}@example.com", $asVisitor('203.0.113.5'))->status();
        }
        $this->assertContains(429, $statuses);

        // …and a different visitor arriving through the same web server is unaffected.
        $this->login('someone@example.com', $asVisitor('198.51.100.7'))->assertStatus(422);
    }

    public function test_a_wrong_secret_is_ignored(): void
    {
        $this->probe([
            'X-Web-Tier-Auth' => 'not-the-secret-but-long-enough-0123456789',
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_a_missing_secret_header_is_ignored(): void
    {
        $this->probe(['X-Client-IP' => '198.51.100.7'])->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_nothing_is_trusted_when_the_api_has_no_secret_configured(): void
    {
        config(['zdravje.web_tier.secret' => null]);

        // An empty presented value must not "match" an empty configured one.
        $this->probe(['X-Web-Tier-Auth' => '', 'X-Client-IP' => '198.51.100.7'])
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_a_secret_shorter_than_the_minimum_is_treated_as_unset(): void
    {
        config(['zdravje.web_tier.secret' => 'short-secret']);

        $this->probe(['X-Web-Tier-Auth' => 'short-secret', 'X-Client-IP' => '198.51.100.7'])
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_a_malformed_address_is_ignored_even_with_the_secret(): void
    {
        $this->probe([
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7, 10.0.0.1',
        ])->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_a_direct_caller_cannot_pick_its_own_bucket_with_the_header(): void
    {
        // Rotating X-Client-IP without the secret must not mint fresh buckets.
        // One window: on a slow machine the loop must not outlive the minute.
        $this->freezeTime();
        $statuses = [];
        foreach (range(1, 45) as $i) {
            $statuses[] = $this->login("probe{$i}@example.com", ['X-Client-IP' => "203.0.113.{$i}"])->status();
        }

        $this->assertContains(429, $statuses, 'Spoofed X-Client-IP values must share one bucket.');
    }

    public function test_a_spoofed_header_does_not_override_trusted_proxy_resolution(): void
    {
        // Existing behaviour without the secret: TrustProxies decides.
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->probe([
            'X-Forwarded-For' => '192.0.2.10',
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '192.0.2.10']);
    }

    public function test_the_authenticated_address_wins_over_a_trusted_forwarded_chain(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->probe([
            'X-Forwarded-For' => '192.0.2.10',
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '198.51.100.7']);
    }

    public function test_the_scheme_the_trusted_edge_reported_survives_the_rewrite(): void
    {
        // Signed and generated URLs depend on the scheme; replacing the client
        // address must not quietly downgrade an HTTPS request to HTTP.
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->probe([
            'X-Forwarded-Proto' => 'https',
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
    }

    public function test_an_untrusted_scheme_header_stays_untrusted(): void
    {
        $this->probe([
            'X-Forwarded-Proto' => 'https',
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '198.51.100.7',
        ])->assertJson(['ip' => '198.51.100.7', 'secure' => false]);
    }

    public function test_an_ipv6_visitor_is_accepted(): void
    {
        $this->probe([
            'X-Web-Tier-Auth' => self::SECRET,
            'X-Client-IP' => '2001:db8::7',
        ])->assertJson(['ip' => '2001:db8::7']);
    }
}
