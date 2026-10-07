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
use App\Support\ReviewHelpfulVotes;
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

    public function test_the_digest_respects_the_global_switch(): void
    {
        $review = $this->review();
        $this->reportViews([$review->id]);
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'impact_digest' => true, 'email_enabled' => false]);

        $this->artisan('notifications:send-impact-digest', ['--month' => now()->format('Y-m')])->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
