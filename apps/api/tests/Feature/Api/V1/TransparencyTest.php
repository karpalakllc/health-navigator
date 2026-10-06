<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RemovalCategory;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use App\Support\TransparencyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * GET /transparency: monthly moderation figures, correct per month and
 * cached for an hour.
 */
class TransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function at(string $time): void
    {
        $this->travelTo($time);
    }

    /**
     * @return array<string, mixed>
     */
    private function month(array $payload, string $month): array
    {
        foreach ($payload['data']['months'] as $row) {
            if ($row['month'] === $month) {
                return $row;
            }
        }

        $this->fail("No row for {$month}");
    }

    public function test_monthly_figures_count_received_published_refused_removed_and_reports(): void
    {
        $moderator = User::factory()->create();
        $doctor = Doctor::factory()->create();

        // September: three reviews in; one approved after 10 h, one refused after 2 h.
        $this->at('2026-09-10 08:00:00');
        $approved = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $refused = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        // Published without a moderator's decision: no moderation time.
        $other = Review::factory()->approved()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $this->at('2026-09-10 10:00:00');
        $refused->reject($moderator, 'Не е во согласност со правилата.');
        $this->at('2026-09-10 18:00:00');
        $approved->approve($moderator);

        // October: it is reported (abuse) and removed; a second report is kept.
        $this->at('2026-10-02 09:00:00');
        $report = ContentReport::factory()->about($approved)->create(['reason' => ReportReason::Abuse]);
        $keptReport = ContentReport::factory()->about($other)->create(['reason' => ReportReason::Spam]);
        $this->at('2026-10-03 09:00:00');
        $report->hideContent($moderator, 'note');
        $keptReport->keepContent($moderator);

        // Forum in October: a reply approved by a moderator after 4 h, a topic
        // auto-approved (no moderator: no moderation time), a reply removed.
        $this->at('2026-10-04 08:00:00');
        $autoTopic = ForumTopic::factory()->create();
        $reply = ForumPost::factory()->pending()->create(['forum_topic_id' => $autoTopic->id]);
        $this->at('2026-10-04 12:00:00');
        $reply->approve($moderator);
        $reply->reject($moderator, 'note', afterReport: true, category: RemovalCategory::PersonalData);

        $this->at('2026-10-06 12:00:00');
        $payload = $this->getJson('/api/v1/transparency')->assertOk()->json();

        $this->assertCount(12, $payload['data']['months']);
        $this->assertSame('2026-10', $payload['data']['months'][0]['month']);
        $this->assertSame('2025-11', $payload['data']['months'][11]['month']);

        $september = $this->month($payload, '2026-09');
        $this->assertSame(3, $september['reviews']['received']);
        // Published in September (one later removed): still counted as published.
        $this->assertSame(2, $september['reviews']['published']);
        $this->assertSame(1, $september['reviews']['rejected']);
        $this->assertSame(0, $september['reviews']['removed']);
        // (10 h + 2 h) / 2
        $this->assertEqualsWithDelta(6.0, $september['reviews']['average_moderation_hours'], 0.01);

        $october = $this->month($payload, '2026-10');
        $this->assertSame(0, $october['reviews']['received']);
        $this->assertSame(1, $october['reviews']['removed']);
        $this->assertSame(1, $october['reviews']['removed_by_category']['abuse']);
        $this->assertSame(0, $october['reviews']['removed_by_category']['illegal']);
        $this->assertNull($october['reviews']['average_moderation_hours']);

        // The topic and the reply.
        $this->assertSame(2, $october['forum']['received']);
        $this->assertSame(2, $october['forum']['published']);
        $this->assertSame(1, $october['forum']['removed_by_category']['personal_data']);
        $this->assertEqualsWithDelta(4.0, $october['forum']['average_moderation_hours'], 0.01);

        $this->assertSame(['received' => 2, 'resolved' => 2, 'removed' => 1, 'kept' => 1], $october['reports']);
        $this->assertSame(ReportStatus::Kept, $keptReport->fresh()->status);
        $this->assertNotNull($autoTopic->fresh()->published_at);

        // Nothing identifying in the payload.
        $json = json_encode($payload);
        $this->assertStringNotContainsString($moderator->email, (string) $json);
        $this->assertStringNotContainsString('note', (string) $json);
    }

    public function test_figures_are_cached_for_an_hour(): void
    {
        $this->at('2026-10-06 12:00:00');
        $doctor = Doctor::factory()->create();

        $this->getJson('/api/v1/transparency')->assertOk()->assertJsonPath('data.months.0.reviews.received', 0);

        Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);

        $this->at('2026-10-06 12:59:00');
        $this->getJson('/api/v1/transparency')->assertOk()->assertJsonPath('data.months.0.reviews.received', 0);

        $this->at('2026-10-06 13:01:00');
        $this->getJson('/api/v1/transparency')->assertOk()->assertJsonPath('data.months.0.reviews.received', 1);
        $this->assertSame(3600, TransparencyStats::CACHE_SECONDS);
    }
}
