<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B1: sign-in reaches the API through the web tier's route handler, so without a
 * forwarded client address every login on the platform lands in one bucket —
 * and ThrottleRequests counts successes too. Five sign-ins a minute, site-wide.
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

    private function makeUser(string $email): void
    {
        User::factory()->create([
            'email' => $email,
            'password' => 'sufficiently1long',
            'email_verified_at' => now(),
        ]);
    }

    public function test_failed_logins_for_one_account_do_not_lock_out_another(): void
    {
        $this->makeUser('victim@example.com');

        // Five wrong-password attempts against unrelated accounts, all relayed by
        // the web tier but carrying distinct client addresses.
        foreach (range(0, 4) as $i) {
            $this->withHeaders(['X-Forwarded-For' => "203.0.113.{$i}"])
                ->postJson('/api/v1/auth/login', [
                    'email' => "attacker{$i}@example.com",
                    'password' => 'wrong-password-here',
                ])
                ->assertStatus(422);
        }

        $this->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'sufficiently1long',
            ])
            ->assertOk();
    }

    public function test_repeated_attempts_on_one_account_are_still_stopped(): void
    {
        $this->makeUser('target@example.com');

        // Distinct addresses each time — the per-account limit must still bite,
        // otherwise distributed credential stuffing walks straight through.
        foreach (range(1, 5) as $i) {
            $this->withHeaders(['X-Forwarded-For' => "203.0.113.{$i}"])
                ->postJson('/api/v1/auth/login', [
                    'email' => 'target@example.com',
                    'password' => 'wrong-password-here',
                ])
                ->assertStatus(422);
        }

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.99'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'target@example.com',
                'password' => 'sufficiently1long',
            ])
            ->assertStatus(429);
    }
}
