<?php

namespace Tests\Feature\Api\V1;

use App\Mail\ImpactDigestMail;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\Review;
use App\Models\User;
use App\Support\ForumPostHelpfulVotes;
use App\Support\ReviewHelpfulVotes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * W8-B impact: „Прикажана N пати“ (once a day per network, the author and
 * unpublished reviews never count), the author's view in „Мои рецензии“ and
 * the opt-in monthly digest.
 */
class ReviewImpactTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-Z360-Consent', 'statistics');
        Mail::fake();
        $this->doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $this->author = User::factory()->create();
    }

    private function review(array $attributes = []): Review
    {
        return Review::factory()->approved()->create([
            'user_id' => $this->author->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $this->doctor->id,
            ...$attributes,
        ]);
    }

    private function reportViews(array $ids, string $ip = '203.0.113.7')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/reviews/views', ['ids' => $ids]);
    }

    public function test_no_consent_header_counts_nothing_and_a_privacy_signal_does_not_block_consent(): void
    {
        $review = $this->review();
        $ip = ['REMOTE_ADDR' => '198.51.100.9'];

        $this->flushHeaders();
        $this->withServerVariables($ip)->postJson('/api/v1/reviews/views', ['ids' => [$review->id]])->assertNoContent();
        $this->assertSame(0, (int) $review->fresh()->view_count);

        // Explicit consent overrides Global Privacy Control / Do Not Track.
        $this->withHeaders(['X-Z360-Consent' => 'statistics', 'Sec-GPC' => '1', 'DNT' => '1'])->withServerVariables($ip)
            ->postJson('/api/v1/reviews/views', ['ids' => [$review->id]])
            ->assertOk()->assertJsonPath('data.counted', 1);
        $this->assertSame(1, (int) $review->fresh()->view_count);

        // Approved, but on a profile that is not published: not counted.
        $hidden = $this->review(['reviewable_id' => Doctor::factory()->unpublished()->create()->id]);
        $this->reportViews([$hidden->id])->assertJsonPath('data.counted', 0);
    }

    public function test_a_view_counts_once_a_day_per_network(): void
    {
        $review = $this->review();

        $this->reportViews([$review->id])->assertOk()->assertJsonPath('data.counted', 1);
        $this->reportViews([$review->id])->assertOk()->assertJsonPath('data.counted', 0);
        $this->reportViews([$review->id], '198.51.100.4')->assertJsonPath('data.counted', 1);
        // The same IPv6 /64 is one network.
        $this->reportViews([$review->id], '2001:db8:1:2::1')->assertJsonPath('data.counted', 1);
        $this->reportViews([$review->id], '2001:db8:1:2::ffff')->assertJsonPath('data.counted', 0);

        $this->assertSame(3, $review->fresh()->view_count);
        $this->assertSame(3, (int) DB::table('review_views_monthly')->where('review_id', $review->id)->value('views'));

        $this->travel(1)->days();
        $this->reportViews([$review->id])->assertJsonPath('data.counted', 1);
        $this->assertSame(4, $review->fresh()->view_count);
    }

    public function test_unpublished_reviews_and_the_authors_own_views_do_not_count(): void
    {
        $published = $this->review();
        $pending = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);

        $this->reportViews([$pending->id, 999999])->assertJsonPath('data.counted', 0);

        Sanctum::actingAs($this->author);
        $this->reportViews([$published->id])->assertJsonPath('data.counted', 0);

        $this->assertSame(0, $published->fresh()->view_count);
        $this->assertSame(0, $pending->fresh()->view_count);
    }

    public function test_the_report_is_bounded(): void
    {
        $this->reportViews(range(1, 31))->assertUnprocessable();
        $this->reportViews([])->assertUnprocessable();
        $this->reportViews(['x'])->assertUnprocessable();
    }

    public function test_my_reviews_show_the_impact_and_the_public_reply_only(): void
    {
        $answered = $this->review(['view_count' => 12, 'helpful_count' => 3]);
        $answered->respond(User::factory()->admin()->create(), 'Ви благодариме.');
        $pendingReply = $this->review(['reviewable_id' => Doctor::factory()->create()->id, 'view_count' => 2]);
        $pendingReply->replyAsDoctor(User::factory()->create(), 'Чека модерација.', requiresModeration: true);
        $waiting = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => Doctor::factory()->create()->id]);
        Sanctum::actingAs($this->author);

        $response = $this->getJson('/api/v1/me/reviews')->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame(['views' => 12, 'helpful' => 3], $byId[$answered->id]['impact']);
        $this->assertSame('Ви благодариме.', $byId[$answered->id]['reply']['body']);
        $this->assertSame('staff', $byId[$answered->id]['reply']['source']);
        $this->assertNull($byId[$pendingReply->id]['reply']);
        $this->assertNull($byId[$waiting->id]['impact']);
        $response->assertJsonPath('meta.impact', ['views' => 14, 'helpful' => 3, 'replies' => 1, 'published' => 2]);
    }

    public function test_the_monthly_digest_goes_only_to_members_who_opted_in_and_had_activity(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 15));
        $review = $this->review();
        $this->reportViews([$review->id]);
        ReviewHelpfulVotes::add($review, User::factory()->create());
        $category = ForumCategory::factory()->create();
        $topic = ForumTopic::factory()->create(['user_id' => $this->author->id, 'forum_category_id' => $category->id, 'status' => 'approved', 'published_at' => now()]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'status' => 'approved', 'published_at' => now()]);

        $quiet = User::factory()->create();
        $notOptedIn = User::factory()->create();
        Review::factory()->approved()->create(['user_id' => $notOptedIn->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id, 'view_count' => 5]);
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'impact_digest' => true]);
        NotificationPreference::query()->create(['user_id' => $quiet->id, 'impact_digest' => true]);

        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();

        Mail::assertQueued(ImpactDigestMail::class, 1);
        Mail::assertQueued(ImpactDigestMail::class, fn (ImpactDigestMail $mail): bool => $mail->hasTo($this->author->email)
            && $mail->monthLabel === 'септември 2026'
            && $mail->stats['review_views'] === 1
            && $mail->stats['helpful_votes'] === 1
            && $mail->stats['forum_replies_received'] === 1
            && $mail->unsubscribeType === 'impact_digest');
        $this->assertSame(1, MemberNotification::query()->where('type', 'impact_digest')->count());
    }

    /**
     * Each member gets a month once: a rerun, a manual --month and the daily
     * catch-up send nothing more; a 1st the machine missed is caught up once.
     * Months are Macedonian time.
     */
    public function test_the_digest_is_sent_once_per_member_and_month_and_catches_up_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00', 'Europe/Skopje'));
        $review = $this->review();
        $this->reportViews([$review->id]);
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'impact_digest' => true]);

        // 2026-09-30 22:30 UTC is already 1 October in Skopje: the next digest's.
        $this->travelTo(CarbonImmutable::parse('2026-09-30 22:30', 'UTC'));
        ReviewHelpfulVotes::add($review, User::factory()->create());

        // The machine was off on the 1st; the daily run on the 4th catches up.
        $this->travelTo(CarbonImmutable::parse('2026-10-04 09:00', 'Europe/Skopje'));
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();
        $this->artisan('notifications:send-impact-digest', ['--month' => '2026-09'])->assertSuccessful();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00', 'Europe/Skopje'));
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();

        Mail::assertQueued(ImpactDigestMail::class, 1);
        Mail::assertQueued(ImpactDigestMail::class, fn (ImpactDigestMail $mail): bool => $mail->monthLabel === 'септември 2026'
            && $mail->stats['review_views'] === 1
            && $mail->stats['helpful_votes'] === 0);
        $this->assertSame(1, MemberNotification::query()->where('type', 'impact_digest')->count());
        $this->assertSame('2026-09', NotificationPreference::query()->find($this->author->id)?->impact_digest_month);

        // October: the vote of the 1st (Skopje) and the October views bucket.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00', 'Europe/Skopje'));
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();
        Mail::assertQueued(ImpactDigestMail::class, fn (ImpactDigestMail $mail): bool => $mail->monthLabel === 'октомври 2026'
            && $mail->stats['helpful_votes'] === 1);
        Mail::assertQueued(ImpactDigestMail::class, 2);
    }

    public function test_the_digest_respects_the_global_switch(): void
    {
        $review = $this->review();
        $this->reportViews([$review->id]);
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'impact_digest' => true, 'email_enabled' => false]);

        $this->artisan('notifications:send-impact-digest', ['--month' => now()->format('Y-m')])->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_the_digest_counts_forum_helpful_votes_on_the_members_replies(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 15));
        $category = ForumCategory::factory()->create();
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'status' => 'approved', 'published_at' => now()->subMonths(2)]);
        $reply = ForumPost::factory()->create(['forum_topic_id' => $topic->id, 'user_id' => $this->author->id, 'status' => 'approved', 'published_at' => now()->subMonths(2)]);
        ForumPostHelpfulVotes::add($reply, User::factory()->create());
        ForumPostHelpfulVotes::add($reply, User::factory()->create());
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'impact_digest' => true]);

        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        // A vote in October belongs to the next digest.
        ForumPostHelpfulVotes::add($reply, User::factory()->create());
        $this->artisan('notifications:send-impact-digest')->assertSuccessful();

        Mail::assertQueued(ImpactDigestMail::class, fn (ImpactDigestMail $mail): bool => $mail->hasTo($this->author->email)
            && $mail->stats['forum_helpful_votes'] === 2
            && str_contains($mail->render(), 'одговорите во форумот'));
    }
}
