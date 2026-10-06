<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\TriageController;
use App\Http\Requests\Api\V1\StoreTriageAnswersRequest;
use App\Models\TriageFlow;
use App\Models\TriageSession;
use App\Services\Triage\TriageSessionService;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $sessionId = $this->startGuidanceSession();

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

        $sessionId = $this->startGuidanceSession();

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

    public function test_complete_is_refused_when_the_red_flag_screen_was_skipped(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->startSessionAndAnswerAll([
            'age_band' => ['18_64'],
            'concern' => ['general'],
            'severity' => ['mild'],
            'duration' => ['under_24h'],
        ]);

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        $this->assertNull(TriageSession::query()->find($sessionId)->completed_at);

        // Answering the screen (even with no flags) unblocks completion.
        $this->putJson("/api/v1/triage/sessions/{$sessionId}/answers", [
            'answers' => [['step_key' => 'red_flags', 'values' => []]],
        ])->assertOk();

        $this->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.outcome.outcome_code', 'general_information');
    }

    public function test_answer_payload_size_and_duplicates_are_bounded(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionId = $this->startGuidanceSession();
        $url = "/api/v1/triage/sessions/{$sessionId}/answers";

        $tooMany = array_map(
            fn (int $i): array => ['step_key' => "step_{$i}", 'values' => []],
            range(1, StoreTriageAnswersRequest::MAX_ANSWERS + 1),
        );
        $this->putJson($url, ['answers' => $tooMany])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers');

        $this->putJson($url, ['answers' => [[
            'step_key' => 'red_flags',
            'values' => array_map(fn (int $i): string => "v{$i}", range(1, StoreTriageAnswersRequest::MAX_VALUES_PER_ANSWER + 1)),
        ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.0.values');

        $this->putJson($url, ['answers' => [
            ['step_key' => 'severity', 'values' => ['mild']],
            ['step_key' => 'severity', 'values' => ['severe']],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.0.step_key');

        $this->putJson($url, ['answers' => [
            ['step_key' => 'concern', 'values' => ['general', 'general']],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.0.values.0');
    }

    public function test_malformed_session_id_is_a_404_without_touching_the_database(): void
    {
        $this->seed(TriageSeeder::class);

        $sessionQueries = 0;
        DB::listen(function ($query) use (&$sessionQueries): void {
            if (str_contains($query->sql, 'triage_sessions')) {
                $sessionQueries++;
            }
        });

        $this->postJson('/api/v1/triage/sessions/not-a-uuid/complete')->assertNotFound();
        $this->postJson('/api/v1/triage/sessions/not-a-uuid/emergency')->assertNotFound();
        $this->putJson('/api/v1/triage/sessions/not-a-uuid/answers', [
            'answers' => [['step_key' => 'red_flags', 'values' => []]],
        ])->assertNotFound();

        // PostgreSQL raises on a malformed uuid literal, so it must never reach SQL.
        $this->assertSame(0, $sessionQueries);
    }

    /**
     * Starts a session and sends its secret on every later request of the test.
     */
    private function startGuidanceSession(): string
    {
        $response = $this->postJson('/api/v1/triage/sessions', [
            'accepted_terms' => true,
        ])->assertCreated();

        $this->withHeader(TriageController::TOKEN_HEADER, (string) $response->json('data.session_token'));

        return (string) $response->json('data.session_id');
    }

    /**
     * @param  array<string, list<string>>  $answersMap
     */
    private function startSessionAndAnswerAll(array $answersMap): string
    {
        $sessionId = $this->startGuidanceSession();

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
