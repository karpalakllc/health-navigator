<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_repeated_failures_on_one_account_are_stopped_across_addresses(): void
    {
        $this->makeUser('target@example.com');

        foreach (range(1, 5) as $i) {
            $this->tryLogin('target@example.com', 'wrong-password-here', "203.0.113.{$i}")
                ->assertStatus(422);
        }

        // Distinct address each time, so only an account-keyed limit catches this.
        $this->tryLogin('target@example.com', 'wrong-password-here', '203.0.113.99')
            ->assertStatus(429);
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

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
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

        // One from registration plus two more before the address hits its ceiling.
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
    }
}
