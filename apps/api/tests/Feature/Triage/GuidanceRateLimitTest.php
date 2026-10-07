<?php

namespace Tests\Feature\Triage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Triage\Concerns\PublishesGuidanceFlows;
use Tests\TestCase;

/**
 * A family or a shared office network starts several guidance sessions in an
 * hour; the start limiter allows 30 per address, the per-step one stays.
 */
class GuidanceRateLimitTest extends TestCase
{
    use PublishesGuidanceFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRateLimits();
        $this->publishFixtureFlows('example-sore-throat');
    }

    public function test_thirty_sessions_an_hour_start_and_the_thirty_first_is_refused(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/triage/v2/sessions', ['accepted_terms' => true])->assertCreated();
        }

        $this->postJson('/api/v1/triage/v2/sessions', ['accepted_terms' => true])->assertStatus(429);

        // Another address is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/v1/triage/v2/sessions', ['accepted_terms' => true])->assertCreated();
    }

    public function test_the_steps_keep_their_own_limiter(): void
    {
        $response = $this->postJson('/api/v1/triage/v2/sessions', ['accepted_terms' => true])->assertCreated();
        $id = $response->json('data.session_id');

        $this->getJson("/api/v1/triage/v2/sessions/{$id}", ['X-Guidance-Token' => $response->json('data.session_token')])
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '600');
    }
}
