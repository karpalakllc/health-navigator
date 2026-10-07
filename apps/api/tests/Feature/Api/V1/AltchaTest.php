<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Support\Altcha\AltchaGuard;
use App\Support\Altcha\AltchaOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SolvesAltcha;
use Tests\TestCase;

/**
 * ALTCHA (W7-C): the challenge endpoint and AltchaGuard's verdicts — a solved
 * challenge from this server opens exactly one request; anything forged,
 * tampered with, expired, too quick or reused does not.
 */
class AltchaTest extends TestCase
{
    use RefreshDatabase;
    use SolvesAltcha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAltcha();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function guard(): AltchaGuard
    {
        return app(AltchaGuard::class);
    }

    public function test_the_challenge_is_signed_pbkdf2_with_an_issue_time_and_is_never_cached(): void
    {
        config(['zdravje.altcha.cost' => 1000, 'zdravje.altcha.expires_minutes' => 30]);
        Carbon::setTestNow('2026-10-20 10:00:00');

        $response = $this->getJson('/api/v1/altcha/challenge')->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response
            ->assertJsonPath('parameters.algorithm', 'PBKDF2/SHA-256')
            ->assertJsonPath('parameters.cost', 1000)
            ->assertJsonPath('parameters.data.iat', Carbon::parse('2026-10-20 10:00:00')->getTimestamp())
            ->assertJsonPath('parameters.expiresAt', Carbon::parse('2026-10-20 10:30:00')->getTimestamp());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $response->json('signature'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $response->json('parameters.keySignature'));
        // No API envelope: the widget reads the challenge at the top level.
        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_a_solved_challenge_verifies_once_and_a_replay_is_refused(): void
    {
        $payload = $this->solvedAltcha();

        $this->assertSame(AltchaOutcome::Verified, $this->guard()->verify($payload));
        $this->assertSame(AltchaOutcome::Replayed, $this->guard()->verify($payload));
    }

    public function test_missing_garbled_and_oversized_payloads_are_refused(): void
    {
        $this->assertSame(AltchaOutcome::Missing, $this->guard()->verify(null));
        $this->assertSame(AltchaOutcome::Missing, $this->guard()->verify(''));
        $this->assertSame(AltchaOutcome::Missing, $this->guard()->verify(['not' => 'a string']));
        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify('not base64 at all!'));
        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify(base64_encode('{"challenge":1}')));
        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify(str_repeat('A', 5000)));
    }

    public function test_a_wrong_solution_is_refused(): void
    {
        $payload = json_decode(base64_decode($this->solvedAltcha()), true);
        $payload['solution']['derivedKey'] = str_repeat('0', 64);

        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify(base64_encode((string) json_encode($payload))));
    }

    public function test_a_challenge_with_easier_parameters_than_issued_is_refused(): void
    {
        // Lowering the work (or dropping the key signature) breaks the
        // server's signature over the parameters.
        $challenge = $this->fetchAltchaChallenge();
        $challenge['parameters']['cost'] = 1;
        $challenge['parameters']['keyPrefix'] = '';

        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify($this->solvedAltcha($challenge)));
    }

    public function test_a_challenge_signed_with_another_key_is_refused(): void
    {
        $foreign = $this->guard()->challenge();
        config(['zdravje.altcha.hmac_key' => 'a-different-secret-for-this-test']);

        $this->assertSame(AltchaOutcome::Invalid, $this->guard()->verify($this->solvedAltcha($foreign)));
    }

    public function test_an_expired_challenge_is_refused(): void
    {
        // Issued 31 minutes ago with a 30-minute life.
        Carbon::setTestNow(now()->subMinutes(31));
        $challenge = $this->guard()->challenge();
        Carbon::setTestNow();

        $this->assertSame(AltchaOutcome::Expired, $this->guard()->verify($this->solvedAltcha($challenge)));
    }

    public function test_a_solution_returned_faster_than_a_person_fills_a_form_is_refused(): void
    {
        config(['zdravje.altcha.min_fill_seconds' => 2]);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $payload = $this->solvedAltcha();

        Carbon::setTestNow('2026-10-20 10:00:01');
        $this->assertSame(AltchaOutcome::TooFast, $this->guard()->verify($payload));

        // Refusing it as too fast did not spend it.
        Carbon::setTestNow('2026-10-20 10:00:02');
        $this->assertSame(AltchaOutcome::Verified, $this->guard()->verify($payload));
    }

    public function test_the_challenge_endpoint_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/v1/altcha/challenge')->assertOk();
        }

        $this->getJson('/api/v1/altcha/challenge')->assertStatus(429);
    }

    public function test_the_middleware_refuses_without_a_solution_and_lets_a_solved_one_through(): void
    {
        $doctor = Doctor::factory()->create();
        $body = ['reason' => 'fake_profile'];

        $this->postJson("/api/v1/doctors/{$doctor->slug}/profile-reports", $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['altcha' => __('api.altcha.failed')]);
        $this->assertDatabaseCount('profile_corrections', 0);

        $payload = $this->solvedAltcha();

        $this->postJson("/api/v1/doctors/{$doctor->slug}/profile-reports", [...$body, 'altcha' => $payload])
            ->assertCreated();
        $this->assertDatabaseCount('profile_corrections', 1);

        // The same solution again: refused, nothing more stored.
        $this->postJson("/api/v1/doctors/{$doctor->slug}/profile-reports", [...$body, 'altcha' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['altcha']);
        $this->assertDatabaseCount('profile_corrections', 1);
    }

    public function test_switched_off_the_middleware_lets_requests_through(): void
    {
        config(['zdravje.altcha.enabled' => false]);
        $doctor = Doctor::factory()->create();

        $this->postJson("/api/v1/doctors/{$doctor->slug}/profile-reports", ['reason' => 'other'])->assertCreated();
    }
}
