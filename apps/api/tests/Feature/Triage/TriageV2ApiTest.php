<?php

namespace Tests\Feature\Triage;

use App\Models\Specialty;
use App\Models\TriageFlowVersion;
use App\Models\TriageOutcomeStat;
use App\Models\TriageSession;
use App\Models\TriageSessionAnswer;
use App\Models\TriageSessionFlow;
use App\Models\User;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Triage\Concerns\PublishesGuidanceFlows;
use Tests\TestCase;

class TriageV2ApiTest extends TestCase
{
    use PublishesGuidanceFlows;
    use RefreshDatabase;

    private const BASE = '/api/v1/triage/v2';

    private string $token = '';

    private string $id = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRateLimits();
    }

    private function openSession(): TestResponse
    {
        $response = $this->postJson(self::BASE.'/sessions', ['accepted_terms' => true])->assertCreated();
        $this->id = $response->json('data.session_id');
        $this->token = $response->json('data.session_token');

        return $response;
    }

    private function guide(string $method, string $path, array $data = []): TestResponse
    {
        return $this->json($method, self::BASE.'/sessions/'.$this->id.$path, $data, ['X-Guidance-Token' => $this->token]);
    }

    private function adult(int $age = 30, string $sex = 'male', array $conditions = []): TestResponse
    {
        return $this->guide('PUT', '/demographics', [
            'age_value' => $age, 'age_unit' => 'years', 'sex' => $sex,
            'pregnancy' => $sex === 'male' ? null : 'not_pregnant', 'conditions' => $conditions,
        ])->assertOk();
    }

    private function answer(string $flow, string $node, array $values): TestResponse
    {
        return $this->guide('PUT', '/answer', ['flow' => $flow, 'node' => $node, 'values' => $values]);
    }

    public function test_catalog_lists_only_published_flows_and_no_rules(): void
    {
        $this->publishFixtureFlows('example-sore-throat');

        $response = $this->getJson(self::BASE.'/catalog')->assertOk();

        $this->assertSame(['example-sore-throat'], array_column($response->json('data.flows'), 'key'));
        $this->assertSame(['throat'], $response->json('data.flows.0.body_areas'));
        $json = (string) $response->getContent();

        foreach (['"next"', '"when"', '"nodes"', '"outcomes"', '"scores"', 'red_flags'] as $leak) {
            $this->assertStringNotContainsString($leak, $json);
        }
    }

    public function test_draft_flows_are_hidden_until_a_clinician_review_is_recorded_and_published(): void
    {
        $this->useFixtureFlows();
        $this->artisan('triage:import')->assertSuccessful();

        $this->getJson(self::BASE.'/catalog')->assertNotFound();
        // Nothing to guide with: no session either (fail closed).
        $this->postJson(self::BASE.'/sessions', ['accepted_terms' => true])->assertNotFound();
        $this->assertSame(['draft', 'draft'], TriageFlowVersion::query()->pluck('status')->all());
    }

    public function test_session_requires_accepted_terms_and_is_never_linked_to_an_account(): void
    {
        $this->publishFixtureFlows('example-sore-throat');

        $this->postJson(self::BASE.'/sessions', ['accepted_terms' => false])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create());
        $this->openSession()->assertJsonPath('data.state.stage', 'demographics');

        $session = TriageSession::query()->findOrFail($this->id);
        $this->assertSame(TriageSession::ENGINE_V2, $session->engine);
        $this->assertNull($session->triage_flow_id);
        $this->assertArrayNotHasKey('user_id', $session->getAttributes());
        $this->assertNotSame($this->token, $session->token_hash);
    }

    public function test_the_session_secret_is_required_and_v1_sessions_are_not_reachable(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();

        $this->getJson(self::BASE.'/sessions/'.$this->id)->assertNotFound();
        $this->getJson(self::BASE.'/sessions/'.$this->id, ['X-Guidance-Token' => 'wrong'])->assertNotFound();
        $this->getJson(self::BASE.'/sessions/not-a-uuid', ['X-Guidance-Token' => $this->token])->assertNotFound();
        $this->guide('GET', '')->assertOk()->assertJsonPath('data.stage', 'demographics');
    }

    public function test_a_full_flow_reaches_an_outcome_with_reasons_safety_net_and_directory_care(): void
    {
        Specialty::factory()->create(['slug' => 'otorinolaringologija', 'name' => 'Оториноларингологија', 'is_published' => true]);
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();

        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']])
            ->assertOk()
            ->assertJsonPath('data.stage', 'screen');

        $screen = $this->guide('PUT', '/screen', ['red_flags' => []])->assertOk();
        $screen->assertJsonPath('data.stage', 'question')
            ->assertJsonPath('data.node.id', 'q_duration')
            ->assertJsonPath('data.node.unit', 'days')
            ->assertJsonMissingPath('data.node.next');

        $this->answer('example-sore-throat', 'q_duration', ['3'])->assertJsonPath('data.node.id', 'q_fever');
        $this->answer('example-sore-throat', 'q_fever', ['no'])->assertJsonPath('data.node.id', 'q_pain');
        $result = $this->answer('example-sore-throat', 'q_pain', ['9'])->assertOk();

        $result->assertJsonPath('data.stage', 'result')
            ->assertJsonPath('data.level', 'urgent_same_day')
            ->assertJsonPath('data.reason', 'answers')
            ->assertJsonPath('data.outcomes.0.outcome.title', 'Побарајте преглед денес')
            ->assertJsonPath('data.outcomes.0.outcome.care.setting', 'on_call')
            ->assertJsonPath('data.outcomes.0.outcome.care.specialties.0.slug', 'otorinolaringologija');
        $this->assertNotEmpty($result->json('data.outcomes.0.outcome.watch_for'));
        $this->assertNotEmpty($result->json('data.outcomes.0.outcome.reasons'));

        // Completed: answers are refused, the result is idempotent.
        $this->answer('example-sore-throat', 'q_pain', ['1'])->assertUnprocessable();
        $this->guide('GET', '')->assertJsonPath('data.level', 'urgent_same_day');
    }

    public function test_demographics_and_scores_route_conservatively(): void
    {
        $this->publishFixtureFlows('example-sore-throat');

        // 70 years old: escalated past self-care.
        $this->openSession();
        $this->adult(70);
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
        $this->guide('PUT', '/screen', ['red_flags' => []]);
        $this->answer('example-sore-throat', 'q_duration', ['1']);
        $this->answer('example-sore-throat', 'q_fever', ['no']);
        $this->answer('example-sore-throat', 'q_pain', ['2'])->assertJsonPath('data.level', 'see_doctor_24_48h');

        // Two amber features (score) → doctor; one → pharmacy.
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
        $this->guide('PUT', '/screen', ['red_flags' => []]);
        $this->answer('example-sore-throat', 'q_duration', ['1', 'week'])->assertUnprocessable();
        $this->answer('example-sore-throat', 'q_duration', ['1.5'])->assertOk();
        $this->answer('example-sore-throat', 'q_duration', ['8']);
        $this->answer('example-sore-throat', 'q_fever', ['yes']);
        $this->answer('example-sore-throat', 'q_pain', ['3'])->assertJsonPath('data.level', 'see_doctor_24_48h');
    }

    public function test_answers_are_validated_against_the_node_and_never_free_text(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
        $this->guide('PUT', '/screen', ['red_flags' => []]);

        $this->answer('example-sore-throat', 'q_duration', ['боли ме грлото'])->assertUnprocessable();
        $this->answer('example-sore-throat', 'q_duration', ['400'])->assertUnprocessable();
        $this->answer('example-sore-throat', 'q_duration', ['unknown'])->assertOk()->assertJsonPath('data.node.id', 'q_fever');
        $this->answer('example-sore-throat', 'q_fever', ['maybe'])->assertUnprocessable();
        $this->answer('example-sore-throat', 'q_fever', ['unsure'])->assertOk();
        $this->answer('example-sore-throat', 'q_pain', ['11'])->assertUnprocessable();

        // A node that is not current (and not earlier on the path) is refused.
        $this->answer('example-sore-throat', 'q_missing', ['yes'])->assertUnprocessable();

        $stored = TriageSessionAnswer::query()->where('triage_session_id', $this->id)->pluck('values', 'step_key')->all();
        $this->assertSame(['unknown'], $stored['flow.example-sore-throat.q_duration']);
        $this->assertSame(['unsure'], $stored['flow.example-sore-throat.q_fever']);
    }

    public function test_questions_cannot_be_answered_before_the_red_flag_screen(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);

        $this->answer('example-sore-throat', 'q_duration', ['3'])->assertUnprocessable();
    }

    public function test_any_red_flag_ends_the_session_in_an_emergency_with_194_and_112(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $screen = $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']])->assertOk();

        $codes = array_column($screen->json('data.screen'), 'code');
        $this->assertContains('global.chest_pain', $codes);
        $this->assertContains('example-sore-throat.cannot_swallow_saliva', $codes);
        // Population flags are filtered by the demographics.
        $this->assertNotContains('global.infant_fever_under_3m', $codes);
        $this->assertArrayNotHasKey('outcome', $screen->json('data.screen.0'));

        $result = $this->guide('PUT', '/screen', ['red_flags' => ['example-sore-throat.cannot_swallow_saliva']])->assertOk();
        $result->assertJsonPath('data.stage', 'result')
            ->assertJsonPath('data.level', 'emergency_now')
            ->assertJsonPath('data.reason', 'red_flag')
            ->assertJsonPath('data.emergency_stopped', true);
        $this->assertSame(['194', '112'], array_column($result->json('data.outcomes.0.outcome.call'), 'number'));

        // No way back into the questionnaire.
        $this->guide('PUT', '/screen', ['red_flags' => []])->assertUnprocessable();
        $this->answer('example-sore-throat', 'q_duration', ['3'])->assertUnprocessable();
        $this->guide('PUT', '/demographics', ['age_value' => 30, 'age_unit' => 'years', 'sex' => 'male', 'conditions' => []])->assertUnprocessable();
    }

    public function test_self_harm_leads_to_the_crisis_outcome(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);

        $this->guide('PUT', '/screen', ['red_flags' => ['global.chest_pain', 'global.self_harm']])
            ->assertJsonPath('data.level', 'emergency_now')
            ->assertJsonPath('data.outcomes.0.outcome.crisis', true)
            ->assertJsonPath('data.outcomes.0.outcome.call.0.number', '194');
    }

    public function test_infant_and_pregnancy_red_flags_appear_for_those_visitors(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->guide('PUT', '/demographics', [
            'age_value' => 30, 'age_unit' => 'years', 'sex' => 'female', 'pregnancy' => 'unsure', 'conditions' => [],
        ])->assertOk();
        $codes = array_column($this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']])->json('data.screen'), 'code');

        $this->assertContains('global.pregnancy_bleeding_pain', $codes);
        $this->assertNotContains('global.infant_very_unwell', $codes);

        // The sore-throat example is not for infants.
        $this->guide('PUT', '/demographics', ['age_value' => 6, 'age_unit' => 'weeks', 'sex' => 'female', 'conditions' => []])->assertOk();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']])->assertUnprocessable();
    }

    public function test_the_emergency_shortcut_works_at_any_stage(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();

        $this->guide('POST', '/emergency')
            ->assertOk()
            ->assertJsonPath('data.level', 'emergency_now')
            ->assertJsonPath('data.reason', 'shortcut')
            ->assertJsonPath('data.outcomes.0.outcome.call.1.number', '112');

        $this->assertSame(1, TriageOutcomeStat::query()->where('flow_key', '_none')->where('outcome_id', 'emergency_shortcut')->value('count'));
    }

    public function test_several_symptoms_run_most_urgent_first_and_the_result_is_the_most_urgent(): void
    {
        $this->publishFixtureFlows('example-sore-throat', 'example-cough');
        $this->openSession();
        $this->adult();

        $screen = $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat', 'example-cough']])->assertOk();
        $this->assertSame(['example-cough', 'example-sore-throat'], array_column($screen->json('data.flows'), 'key'));

        $this->guide('PUT', '/screen', ['red_flags' => []])
            ->assertJsonPath('data.flow.key', 'example-cough')
            ->assertJsonPath('data.node.type', 'info');
        $this->answer('example-cough', 'i_intro', ['seen'])->assertJsonPath('data.node.id', 'q_symptoms');
        $this->answer('example-cough', 'q_symptoms', ['fever', 'none'])->assertUnprocessable();
        $this->answer('example-cough', 'q_symptoms', ['fever'])
            ->assertJsonPath('data.flow.key', 'example-sore-throat')
            ->assertJsonPath('data.node.id', 'q_duration');
        $this->answer('example-sore-throat', 'q_duration', ['2']);
        $this->answer('example-sore-throat', 'q_fever', ['no']);
        $result = $this->answer('example-sore-throat', 'q_pain', ['1'])->assertOk();

        $result->assertJsonPath('data.level', 'see_gp_this_week')
            ->assertJsonPath('data.outcomes.0.flow.key', 'example-cough')
            ->assertJsonPath('data.outcomes.1.outcome.level', 'self_care_with_safety_net');
        $this->assertSame('example-cough:o_gp', TriageSession::query()->findOrFail($this->id)->outcome_code);
    }

    public function test_an_emergency_outcome_in_one_flow_stops_the_rest(): void
    {
        $this->publishFixtureFlows('example-sore-throat', 'example-cough');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat', 'example-cough']]);
        $this->guide('PUT', '/screen', ['red_flags' => []]);
        $this->answer('example-cough', 'i_intro', ['seen']);

        $result = $this->answer('example-cough', 'q_symptoms', ['breathless'])->assertOk();

        $result->assertJsonPath('data.stage', 'result')
            ->assertJsonPath('data.level', 'emergency_now')
            ->assertJsonCount(1, 'data.outcomes');
        $this->assertNull(TriageSessionFlow::query()->where('flow_key', 'example-sore-throat')->value('outcome_id'));
    }

    public function test_going_back_re_answers_and_discards_later_answers(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
        $this->guide('PUT', '/screen', ['red_flags' => []]);
        $this->answer('example-sore-throat', 'q_duration', ['2']);
        $state = $this->answer('example-sore-throat', 'q_fever', ['yes'])->assertJsonPath('data.node.id', 'q_pain');
        $this->assertSame(['q_duration', 'q_fever'], array_column(array_column($state->json('data.path'), 'node'), 'id'));

        // Back to the first question: q_fever is asked again, not skipped.
        $this->answer('example-sore-throat', 'q_duration', ['4'])->assertJsonPath('data.node.id', 'q_fever');
        $this->assertFalse(TriageSessionAnswer::query()->where('triage_session_id', $this->id)->where('step_key', 'flow.example-sore-throat.q_fever')->exists());

        // Changing the symptoms or the demographics starts the screen over.
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']])->assertJsonPath('data.stage', 'screen');
        $this->assertSame(0, TriageSessionAnswer::query()->where('triage_session_id', $this->id)->where('step_key', 'like', 'flow.%')->count());
    }

    public function test_outcomes_are_counted_per_flow_and_week_without_any_link_to_the_session(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00'); // a Wednesday
        $this->publishFixtureFlows('example-sore-throat');

        foreach ([['9'], ['9']] as $pain) {
            $this->openSession();
            $this->adult();
            $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
            $this->guide('PUT', '/screen', ['red_flags' => []]);
            $this->answer('example-sore-throat', 'q_duration', ['1']);
            $this->answer('example-sore-throat', 'q_fever', ['no']);
            $this->answer('example-sore-throat', 'q_pain', $pain);
        }

        $stat = TriageOutcomeStat::query()->sole();
        $this->assertSame('2026-10-05', $stat->week_start->toDateString());
        $this->assertSame(['example-sore-throat', 'o_same_day', 'urgent_same_day', 2], [$stat->flow_key, $stat->outcome_id, $stat->outcome_level, $stat->count]);
        $this->assertSame(
            ['id', 'week_start', 'flow_key', 'outcome_id', 'outcome_level', 'count', 'created_at', 'updated_at'],
            array_keys($stat->getAttributes()),
        );
    }

    public function test_purge_deletes_v2_sessions_with_their_flows_but_keeps_the_counts(): void
    {
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();
        $this->guide('PUT', '/symptoms', ['flows' => ['example-sore-throat']]);
        $this->guide('POST', '/emergency');
        TriageSession::query()->whereKey($this->id)->update(['created_at' => now()->subDays(91)]);

        $this->artisan('triage:purge-old-sessions')->assertSuccessful();

        $this->assertSame(0, TriageSession::query()->count());
        $this->assertSame(0, TriageSessionFlow::query()->count());
        $this->assertSame(0, TriageSessionAnswer::query()->count());
        $this->assertSame(1, TriageOutcomeStat::query()->count());
    }

    public function test_no_match_offers_the_fallback_flow_through_the_escalation_seam(): void
    {
        config(['triage.fallback_flow' => 'example-sore-throat']);
        $this->publishFixtureFlows('example-sore-throat');
        $this->openSession();
        $this->adult();

        $this->guide('POST', '/no-match', ['body_area' => 'head'])
            ->assertOk()
            ->assertJsonPath('data.suggested', ['example-sore-throat']);
    }

    public function test_the_v1_flow_and_api_keep_working_next_to_v2(): void
    {
        $this->seed(TriageSeeder::class);
        $this->publishFixtureFlows('example-sore-throat');

        $this->getJson('/api/v1/triage/flow')->assertOk()->assertJsonPath('data.title', TriageSeeder::TITLE);
    }
}
