<?php

namespace Tests\Feature\Api\V1;

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

    public function test_votes_add_up_per_item_and_day(): void
    {
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => true])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guide:kako-do-uput', 'helpful' => false])->assertNoContent();
        $this->postJson('/api/v1/feedback', ['item' => 'guidance:headache:outcome:see_gp_this_week', 'helpful' => true])->assertNoContent();

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
        foreach ([['start', 0], ['q-red-flags', 1], ['q-red-flags', 1], ['outcome:self_care_with_safety_net', 2]] as [$step, $depth]) {
            $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:headache', 'step' => $step, 'depth' => $depth])->assertNoContent();
        }

        $this->assertSame(2, (int) DB::table('funnel_step_counters')->where('step', 'q-red-flags')->value('reached'));

        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:headache', 'step' => 'Што ве боли?', 'depth' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guide:x', 'step' => 'start', 'depth' => 0])->assertUnprocessable();
        $this->postJson('/api/v1/feedback/steps', ['funnel' => 'guidance:headache', 'step' => 'start', 'depth' => 999])->assertUnprocessable();
    }

    public function test_nothing_about_the_visitor_is_stored(): void
    {
        foreach (['feedback_counters', 'feedback_reason_counters', 'funnel_step_counters'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertNotContains($column, ['ip', 'ip_address', 'user_id', 'session_id', 'user_agent', 'created_at', 'text'], "{$table}.{$column}");
            }
        }
    }

    public function test_votes_are_rate_limited_per_network(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/feedback', ['item' => 'guide:x', 'helpful' => true])->assertNoContent();
        }

        $this->postJson('/api/v1/feedback', ['item' => 'guide:x', 'helpful' => true])->assertTooManyRequests();
        $this->assertSame(20, (int) DB::table('feedback_counters')->value('helpful'));

        // Another network is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/v1/feedback', ['item' => 'guide:x', 'helpful' => true])->assertNoContent();
    }
}
