<?php

namespace Tests\Feature\Api\V1;

use App\Models\TriageSession;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regressions for the Part I security findings: account enumeration on password
 * reset (M1), the optional-auth middleware bypassing Sanctum's token checks
 * (M2), and triage sessions having no ownership binding (L4).
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('api-login');
    }

    // ---- M1: password reset must not confirm whether an account exists ----

    public function test_password_reset_response_is_identical_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com']);
        RateLimiter::clear('api-login');
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();

        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'The response differs between a registered and an unregistered address, '
            .'which makes the endpoint an account-existence oracle.',
        );
    }

    public function test_password_reset_still_sends_mail_to_a_real_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'real@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_sends_nothing_for_an_unknown_address(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    // ---- M2: expired tokens must not authenticate ----

    public function test_expired_token_is_not_accepted_on_optional_auth_routes(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->create();
        $token = $user->createToken('test');

        // Age the token past the configured expiry window.
        $token->accessToken->forceFill([
            'created_at' => Carbon::now()->subMinutes(120),
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    // ---- L4: a session that belongs to someone is only reachable by them ----

    public function test_another_user_cannot_complete_an_owned_triage_session(): void
    {
        $this->seed(TriageSeeder::class);

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        // Real bearer tokens rather than actingAs(): the sanctum guard memoises
        // its resolved user, and a test reuses one application instance across
        // requests, so actingAs() would leave the first caller in place.
        $ownerToken = $owner->createToken('owner')->plainTextToken;
        $intruderToken = $intruder->createToken('intruder')->plainTextToken;

        $sessionId = $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson('/api/v1/triage/sessions', ['accepted_terms' => true])
            ->assertCreated()
            ->json('data.session_id');

        $this->assertSame($owner->id, TriageSession::query()->findOrFail($sessionId)->user_id);

        // A test reuses one application instance across requests, and the sanctum
        // guard memoises its resolved user — so without this the second request
        // would still be authenticated as the owner and the test would pass for
        // the wrong reason. Production rebuilds the container per request.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$intruderToken}")
            ->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertNotFound();
    }

    public function test_anonymous_triage_sessions_remain_reachable_without_an_account(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->postJson('/api/v1/triage/sessions', ['accepted_terms' => true])
            ->assertCreated()
            ->json('data.session_id');

        $this->assertNull(TriageSession::query()->findOrFail($sessionId)->user_id);

        // Guidance is deliberately usable without signing in.
        $this->postJson("/api/v1/triage/sessions/{$sessionId}/emergency")
            ->assertOk();
    }
}
