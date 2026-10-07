<?php

namespace Tests\Feature\Api\V1;

use App\Services\Triage\V2\FlowImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * „Дали ви помогна?“ votes, reasons and step counters (docs/urgent-care.md
 * § Feedback): anonymous daily counters from closed vocabularies.
 */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The browser sends this with every statistics request once the
        // visitor accepted statistics (RequireStatisticsConsent).
        $this->withHeader('X-Z360-Consent', 'statistics');
    }

    private function importFixtureFlows(): void
    {
        config(['triage.flows_path' => base_path('tests/Fixtures/triage')]);
        app(FlowImporter::class)->import();
    }

    public function test_step_counters_need_the_consent_header_but_votes_do_not(): void
    {
        $this->importFixtureFlows();
        $this->flushHeaders();
        $step = ['funnel' => 'guidance:example-sore-throat', 'step' => 'start', 'depth' => 0];

        $this->postJson('/api/v1/feedback/steps', $step)->assertNoContent();
        $this->assertSame(0, DB::table('funnel_step_counters')->count());

        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        $this->assertSame(1, DB::table('feedback_counters')->count());
    }

    public function test_votes_add_up_per_item_and_day(): void
    {
        $this->importFixtureFlows();
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => false])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guidance:example-sore-throat:outcome:see_gp_this_week', 'helpful' => true])->assertNoContent();

        $row = DB::table('feedback_counters')->where('item_key', 'guide:kako-do-uput')->first();
        $this->assertSame([2, 1], [(int) $row->helpful, (int) $row->not_helpful]);
        $this->assertSame(now()->toDateString(), substr((string) $row->day, 0, 10));
        $this->assertSame(2, DB::table('feedback_counters')->count());
    }

    public function test_items_are_slugs_in_known_namespaces(): void
    {
        foreach (['', 'guide', 'Guide:x', 'other:x', 'guide:има текст', 'guide:x y', 'guide:a:b:c:d', str_repeat('a', 97)] as $item) {
            $this->postJson('/api/v1/feedback', ['item' => $item, 'helpful' => true])->assertUnprocessable();
        }

        $this->postJson('/api/v1/feedback', ['item' => 'guide:x', 'helpful' => 'maybe'])->assertUnprocessable();
        $this->assertSame(0, DB::table('feedback_counters')->count());
    }

    public function test_reasons_come_from_the_closed_list_for_that_answer(): void
    {
        $this->postJson('/api/v1/feedback/reasons', ['item' => 'urgent-care:bitola', 'helpful' => false, 'reasons' => ['not-found', 'outdated']])
            ->assertNoContent();

        // A „helpful“ reason on a „not helpful“ answer, free text, too many.
        $this->postJson('/api/v1/feedback/reasons', ['item' => 'urgent-care:bitola', 'helpful' => false, 'reasons' => ['clear']])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/reasons', ['item' => 'urgent-care:bitola', 'helpful' => false, 'reasons' => ['Не најдов болница']])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/reasons', ['item' => 'urgent-care:bitola', 'helpful' => false, 'reasons' => ['unclear', 'not-found', 'outdated', 'wrong-info']])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/reasons', ['item' => 'urgent-care:bitola', 'helpful' => false, 'reasons' => ['unclear', 'unclear']])->assertUnprocessable();

        $this->assertSame(
            ['not-found' => 1, 'outdated' => 1],
            DB::table('feedback_reason_counters')->orderBy('reason')->pluck('count', 'reason')->map(fn ($n) => (int) $n)->all(),
        );
    }

    public function test_steps_count_how_far_visitors_get(): void
    {
        $this->importFixtureFlows();

        foreach ([['start', 0], ['q_duration', 1], ['q_duration', 1], ['outcome:self_care_with_safety_net', 2]] as [$step, $depth]) {
            $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:example-sore-throat', 'step' => $step, 'depth' => $depth])->assertNoContent();
        }

        $this->assertSame(2, (int) DB::table('funnel_step_counters')->where('step', 'q_duration')->value('reached'));

        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:example-sore-throat', 'step' => 'Што ве боли?', 'depth' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guide:x', 'step' => 'start', 'depth' => 0])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:example-sore-throat', 'step' => 'start', 'depth' => 999])->assertUnprocessable();
    }

    public function test_well_formed_keys_that_name_nothing_known_are_dropped_without_being_stored(): void
    {
        $this->importFixtureFlows();

        foreach ([
            'guide:invented-slug',
            'urgent-care:nowhere',
            'page:anything',
            'guidance:no-such-flow:outcome:pharmacy_advice',
            'guidance:example-sore-throat:outcome:not_a_level',
            'guidance:example-sore-throat:outcome',
        ] as $item) {
            $this->postJson('/api/v1/feedback', ['item' => $item, 'helpful' => true])->assertNoContent();
            $this->postJson('/api/v1/feedback/reasons', ['item' => $item, 'helpful' => true, 'reasons' => ['clear']])->assertNoContent();
        }

        foreach ([
            ['guidance:no-such-flow', 'start'],
            ['guidance:example-sore-throat', 'q_invented'],
            ['guidance:example-sore-throat', 'outcome:nonsense'],
            ['urgent-care:bitola', 'start'],
        ] as [$funnel, $step]) {
            $this->postJson('/api/v1/feedback/steps', ['funnel' => $funnel, 'step' => $step, 'depth' => 1])->assertNoContent();
        }

        foreach (['feedback_counters', 'feedback_reason_counters', 'funnel_step_counters'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }

        $this->postJson('/api/v1/feedback', ['item' => 'guidance:global:outcome:emergency_now', 'helpful' => true])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'urgent-care:all', 'helpful' => true])->assertNoContent();
        $this->assertSame(2, DB::table('feedback_counters')->count());
    }

    public function test_nothing_about_the_visitor_is_stored(): void
    {
        foreach (['feedback_counters', 'feedback_reason_counters', 'funnel_step_counters'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);

            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertNotContains($column, ['ip', 'ip_address', 'user_id', 'session_id', 'user_agent', 'created_at', 'text'], "{$table}.{$column}");
            }
        }
    }

    public function test_votes_are_rate_limited_per_network(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        }

        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertTooManyRequests();
        $this->assertSame(20, (int) DB::table('feedback_counters')->value('helpful'));

        // Another network is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
    }
}
