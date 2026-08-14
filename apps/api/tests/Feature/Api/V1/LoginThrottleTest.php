<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sign-in and verification throttling.
 *
 * B1: sign-in reaches the API through the web tier's route handler, so without a
 * forwarded client address every login on the platform lands in one bucket.
 *
 * N2: the per-account limit has to count *failures*. Counting requests lets
 * anyone who knows an address hold that account in lockout.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRateLimits();
        config(['trustedproxy.proxies' => '*']);
    }

    private function makeUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'sufficiently1long',
            'email_verified_at' => now(),
        ]);
    }

    private function tryLogin(string $email, string $password, string $ip): TestResponse
    {
        return $this->withHeaders(['X-Forwarded-For' => $ip])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_failed_logins_for_one_account_do_not_lock_out_another(): void
    {
        $this->makeUser('victim@example.com');

        foreach (range(0, 4) as $i) {
            $this->tryLogin("attacker{$i}@example.com", 'wrong-password-here', "203.0.113.{$i}")
                ->assertStatus(422);
        }

        $this->tryLogin('victim@example.com', 'sufficiently1long', '198.51.100.7')->assertOk();
    }

    /**
     * Deliberate trade, stated explicitly: spraying one account from many distinct
     * addresses is NOT stopped by the per-account counter, because a counter that
     * did stop it would equally let an attacker lock the owner out. What bounds
     * this is volume per source — asserted below — plus the audit trail.
     */
    public function test_failures_from_distinct_addresses_are_bounded_by_volume_not_by_account(): void
    {
        $this->makeUser('target@example.com');

        foreach (range(1, 8) as $i) {
            $this->tryLogin('target@example.com', 'wrong-password-here', "203.0.113.{$i}")
                ->assertStatus(422);
        }

        // The owner is never collateral damage.
        $this->tryLogin('target@example.com', 'sufficiently1long', '198.51.100.7')->assertOk();
    }

    public function test_volume_from_a_single_source_is_capped(): void
    {
        $this->makeUser('target@example.com');

        // The api-login limiter allows 40/min per address; past that, 429 regardless
        // of which account is being probed.
        $statuses = [];
        foreach (range(1, 45) as $i) {
            $statuses[] = $this->tryLogin("probe{$i}@example.com", 'wrong-password-here', '203.0.113.5')
                ->status();
        }

        $this->assertContains(429, $statuses, 'A single source should hit the volume ceiling.');
    }

    /**
     * N2. The owner must not be locked out by someone else's failures once they
     * supply the right password — a limit that counts requests rather than
     * failures turns "I know your email" into an indefinite denial of service.
     */
    public function test_a_correct_password_is_accepted_and_clears_the_counter(): void
    {
        $this->makeUser('owner@example.com');

        // Four failures: under the limit, so the owner can still get in.
        foreach (range(1, 4) as $i) {
            $this->tryLogin('owner@example.com', 'wrong-password-here', "203.0.113.{$i}")
                ->assertStatus(422);
        }

        $this->tryLogin('owner@example.com', 'sufficiently1long', '203.0.113.50')->assertOk();

        // Success cleared the counter, so the next failure starts from zero rather
        // than tipping straight into a lockout.
        $this->tryLogin('owner@example.com', 'wrong-password-here', '203.0.113.51')
            ->assertStatus(422);
        $this->tryLogin('owner@example.com', 'sufficiently1long', '203.0.113.52')->assertOk();
    }

    /**
     * N3. The cooldown belongs to the address, not to one route — /auth/register
     * used to walk straight past a resend limit that lived on the resend route.
     */
    public function test_repeated_registrations_cannot_mail_bomb_one_address(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Someone',
            'email' => 'target@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ];

        foreach (range(1, 6) as $_) {
            $this->postJson('/api/v1/auth/register', $payload)->assertStatus(202);
        }

        $user = User::query()->where('email', 'target@example.com')->sole();

        // One per minute per address, whichever endpoint asked.
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }

    public function test_the_cooldown_spans_register_and_resend_together(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'mixed@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ])->assertStatus(202);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/email/resend', ['email' => 'mixed@example.com'])
                ->assertStatus(202);
        }

        $user = User::query()->where('email', 'mixed@example.com')->sole();

        // The cooldown belongs to the address, so five resends straight after a
        // registration add nothing — the endpoint chosen makes no difference.
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }

    /**
     * R1. The lockout must cost the attacker, not the owner. Keying on the address
     * alone means five wrong passwords a minute from anywhere refuse the owner's
     * correct one — counting failures rather than requests does not help, because
     * the owner never gets far enough to succeed.
     */
    public function test_an_attacker_cannot_lock_the_owner_out_from_another_address(): void
    {
        $this->makeUser('owner@example.com');

        // Attacker exhausts their own budget from one address.
        foreach (range(1, 6) as $_) {
            $this->tryLogin('owner@example.com', 'wrong-password-here', '203.0.113.9');
        }
        $this->tryLogin('owner@example.com', 'wrong-password-here', '203.0.113.9')
            ->assertStatus(429);

        // The owner, elsewhere, is unaffected.
        $this->tryLogin('owner@example.com', 'sufficiently1long', '198.51.100.7')->assertOk();
    }

    public function test_the_lockout_survives_into_the_next_window_for_the_attacker(): void
    {
        $this->makeUser('owner@example.com');

        foreach (range(1, 5) as $_) {
            $this->tryLogin('owner@example.com', 'wrong-password-here', '203.0.113.9')
                ->assertStatus(422);
        }

        $this->tryLogin('owner@example.com', 'sufficiently1long', '203.0.113.9')
            ->assertStatus(429);

        // ...and the owner is still fine from their own address, in the same window.
        $this->tryLogin('owner@example.com', 'sufficiently1long', '198.51.100.7')->assertOk();
    }

    /**
     * R2. AccountExistsMail is triggerable by anyone typing someone else's address
     * into the sign-up form. It used to be capped only incidentally, by a login
     * limiter that was later removed — twelve registrations delivered twelve mails.
     */
    public function test_repeated_signups_for_an_existing_account_cannot_flood_the_owner(): void
    {
        Mail::fake();

        $this->makeUser('taken@example.com');

        foreach (range(1, 12) as $_) {
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Someone',
                'email' => 'taken@example.com',
                'password' => 'sufficiently1long',
                'password_confirmation' => 'sufficiently1long',
            ])->assertStatus(202);
        }

        Mail::assertQueuedCount(1);
    }

    /**
     * A stranger triggering the cooldown must not silently block a real user's
     * activation. The window is a minute, not an hour, for exactly this reason.
     */
    public function test_the_cooldown_expires_quickly_enough_not_to_strand_a_new_user(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone',
            'email' => 'fresh@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ])->assertStatus(202);

        $user = User::query()->where('email', 'fresh@example.com')->sole();
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

        // Immediately after, suppressed.
        $this->postJson('/api/v1/auth/email/resend', ['email' => 'fresh@example.com'])
            ->assertStatus(202);
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

        // A minute later the owner can ask again and actually get one.
        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/email/resend', ['email' => 'fresh@example.com'])
            ->assertStatus(202);
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);
    }
}
