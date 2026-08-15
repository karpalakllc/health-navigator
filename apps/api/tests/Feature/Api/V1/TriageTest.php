<?php

namespace Tests\Feature\Api\V1;

use App\Models\TriageFlow;
use App\Services\Triage\TriageSessionService;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRateLimits();
    }

    public function test_flow_returns_published_steps_without_rules(): void
    {
        $this->seed(TriageSeeder::class);

        $response = $this->getJson('/api/v1/triage/flow');

        $response->assertOk()
            ->assertJsonPath('data.title', 'General symptom guidance')
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'intro_body',
                    'red_flags' => [['code', 'label']],
                    'steps' => [['key', 'type', 'label', 'required', 'options']],
                ],
            ]);

        $this->assertArrayNotHasKey('rules', $response->json('data'));
    }

    public function test_flow_returns_404_when_nothing_published(): void
    {
        TriageFlow::query()->create([
            'title' => 'Draft',
            'is_published' => false,
        ]);

        $this->getJson('/api/v1/triage/flow')
            ->assertNotFound()
            ->assertJsonPath('message', 'Symptom guidance is not available.');
    }

    public function test_session_requires_accepted_terms(): void
    {
        $this->seed(TriageSeeder::class);

        $this->postJson('/api/v1/triage/sessions', ['accepted_terms' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accepted_terms']);
    }

    public function test_red_flag_short_circuits_to_emergency_outcome(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->postJson('/api/v1/triage/sessions', [
            'accepted_terms' => true,
        ])->assertCreated()->json('data.session_id');

        $this->putJson("/api/v1/triage/sessions/{$sessionId}/answers", [
            'answers' => [
                ['step_key' => 'red_flags', 'values' => ['chest_pain']],
            ],
        ])->assertOk()
            ->assertJsonPath('data.emergency_stopped', true);

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.outcome.outcome_code', TriageSessionService::EMERGENCY_OUTCOME_CODE);

        $this->assertDatabaseHas('triage_sessions', [
            'id' => $sessionId,
            'emergency_stopped' => true,
            'outcome_code' => TriageSessionService::EMERGENCY_OUTCOME_CODE,
        ]);
    }

    public function test_emergency_endpoint_completes_with_emergency_outcome(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->postJson('/api/v1/triage/sessions', [
            'accepted_terms' => true,
        ])->json('data.session_id');

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/emergency")
            ->assertOk()
            ->assertJsonPath('data.outcome.outcome_code', TriageSessionService::EMERGENCY_OUTCOME_CODE);
    }

    public function test_severe_severity_maps_to_seek_care_soon(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->startSessionAndAnswerAll([
            'red_flags' => [],
            'age_band' => ['under_18'],
            'concern' => ['general'],
            'severity' => ['severe'],
            'duration' => ['under_24h'],
        ]);

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.outcome.outcome_code', 'seek_care_soon');
    }

    public function test_mild_short_duration_defaults_to_general_information(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->startSessionAndAnswerAll([
            'red_flags' => [],
            'age_band' => ['18_64'],
            'concern' => ['general'],
            'severity' => ['mild'],
            'duration' => ['under_24h'],
        ]);

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.outcome.outcome_code', 'general_information');
    }

    public function test_cannot_update_answers_after_completion(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->startSessionAndAnswerAll([
            'red_flags' => [],
            'age_band' => ['18_64'],
            'concern' => ['general'],
            'severity' => ['mild'],
            'duration' => ['under_24h'],
        ]);

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")->assertOk();

        $this->putJson("/api/v1/triage/sessions/{$sessionId}/answers", [
            'answers' => [
                ['step_key' => 'severity', 'values' => ['severe']],
            ],
        ])->assertUnprocessable();
    }

    /**
     * @param  array<string, list<string>>  $answersMap
     */
    private function startSessionAndAnswerAll(array $answersMap): string
    {
        $sessionId = $this->postJson('/api/v1/triage/sessions', [
            'accepted_terms' => true,
        ])->json('data.session_id');

        $payload = [];

        foreach ($answersMap as $stepKey => $values) {
            $payload[] = ['step_key' => $stepKey, 'values' => $values];
        }

        $this->putJson("/api/v1/triage/sessions/{$sessionId}/answers", [
            'answers' => $payload,
        ])->assertOk();

        return $sessionId;
    }
}
