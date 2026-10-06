<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Enums\ForumContentStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Mail\ContentReportOutcomeMail;
use App\Mail\UgcRejectedMail;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentReportTest extends TestCase
{
    use RefreshDatabase;

    private function approvedReview(?Doctor $doctor = null, array $attributes = []): Review
    {
        $doctor ??= Doctor::factory()->create(['slug' => 'ana-petrovska']);

        return Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            ...$attributes,
        ]);
    }

    public function test_a_member_reports_a_published_review_once(): void
    {
        $review = $this->approvedReview();
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->postJson("/api/v1/reviews/{$review->id}/reports", [
            'reason' => 'false_information',
            'note' => '<b>Ова</b> не е точно.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $report = ContentReport::query()->sole();
        $this->assertSame(ReportReason::FalseInformation, $report->reason);
        $this->assertSame(ReportStatus::Open, $report->status);
        $this->assertSame('Ова не е точно.', $report->note);
        $this->assertTrue($report->reportable->is($review));
    }

    public function test_a_repeat_report_is_idempotent(): void
    {
        $review = $this->approvedReview();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertCreated();
        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'abuse', 'note' => 'again'])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->assertSame(1, ContentReport::query()->count());
        $this->assertSame(ReportReason::Spam, ContentReport::query()->sole()->reason);
    }

    public function test_reports_need_a_signed_in_verified_account(): void
    {
        $review = $this->approvedReview();

        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertForbidden();

        $this->assertSame(0, ContentReport::query()->count());
    }

    public function test_reason_and_note_are_validated(): void
    {
        $review = $this->approvedReview();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'boring'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'other', 'note' => str_repeat('а', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->assertSame(0, ContentReport::query()->count());
    }

    public function test_only_publicly_visible_content_can_be_reported(): void
    {
        $doctor = Doctor::factory()->create();
        $pending = Review::factory()->create(['reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id]);
        $onHiddenProfile = $this->approvedReview(Doctor::factory()->create(['is_published' => false]));
        $pendingPost = ForumPost::factory()->pending()->create();
        $hiddenCategory = ForumCategory::factory()->unpublished()->create();
        $topicInHiddenCategory = ForumTopic::factory()->create(['forum_category_id' => $hiddenCategory->id]);
        $postInHiddenCategory = ForumPost::factory()->create(['forum_topic_id' => $topicInHiddenCategory->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/reviews/{$pending->id}/reports", ['reason' => 'spam'])->assertNotFound();
        $this->postJson("/api/v1/reviews/{$onHiddenProfile->id}/reports", ['reason' => 'spam'])->assertNotFound();
        $this->postJson("/api/v1/forum/posts/{$pendingPost->id}/reports", ['reason' => 'spam'])->assertNotFound();
        $this->postJson("/api/v1/forum/posts/{$postInHiddenCategory->id}/reports", ['reason' => 'spam'])->assertNotFound();
        $this->postJson("/api/v1/forum/categories/{$hiddenCategory->slug}/topics/{$topicInHiddenCategory->slug}/reports", ['reason' => 'spam'])
            ->assertNotFound();

        $this->assertSame(0, ContentReport::query()->count());
    }

    public function test_forum_topics_and_replies_can_be_reported(): void
    {
        $topic = ForumTopic::factory()->create();
        $post = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/forum/categories/{$topic->category->slug}/topics/{$topic->slug}/reports", ['reason' => 'abuse'])
            ->assertCreated();
        $this->postJson("/api/v1/forum/posts/{$post->id}/reports", ['reason' => 'personal_data'])
            ->assertCreated();

        $this->assertDatabaseHas('content_reports', ['reportable_type' => ForumTopic::class, 'reportable_id' => $topic->id, 'reason' => 'abuse']);
        $this->assertDatabaseHas('content_reports', ['reportable_type' => ForumPost::class, 'reportable_id' => $post->id, 'reason' => 'personal_data']);
    }

    public function test_reports_are_throttled_per_account(): void
    {
        $review = $this->approvedReview();
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertSuccessful();
        }

        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertTooManyRequests();

        // Someone else is not affected.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/reviews/{$review->id}/reports", ['reason' => 'spam'])->assertCreated();
    }

    public function test_hiding_a_reported_review_unpublishes_it_and_closes_every_open_report(): void
    {
        Mail::fake();
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $review = $this->approvedReview($doctor, ['rating' => 1]);
        $this->approvedReview($doctor, ['rating' => 5]);
        $first = ContentReport::factory()->about($review)->create();
        $second = ContentReport::factory()->about($review)->create(['reason' => ReportReason::Abuse]);
        $moderator = User::factory()->moderator()->create();

        $this->getJson('/api/v1/doctors/ana-petrovska')->assertJsonPath('data.review_summary.count', 2);

        $first->hideContent($moderator);

        $review->refresh();
        $this->assertSame(ReviewStatus::Rejected, $review->status);
        $this->assertSame($moderator->id, $review->moderated_by_id);
        $this->assertNotNull($review->moderated_at);
        $this->assertNotEmpty($review->rejection_note);

        foreach ([$first, $second] as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::Hidden, $report->status);
            $this->assertSame($moderator->id, $report->resolved_by_id);
            $this->assertNotNull($report->resolved_at);
        }

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('meta.rating_counts.1', 0);
        $this->getJson('/api/v1/doctors/ana-petrovska')
            ->assertJsonPath('data.review_summary.count', 1)
            ->assertJsonPath('data.review_summary.average_rating', 5);

        Mail::assertQueued(UgcRejectedMail::class, function (UgcRejectedMail $mail): bool {
            $text = self::text($mail->render());

            return $mail->removed
                && $mail->envelope()->subject === 'Содржината е отстранета — Zdravje360'
                && str_contains($text, 'е отстранета од Zdravje360 по пријава')
                && ! str_contains($text, 'не беше објавена');
        });
    }

    public function test_hiding_a_reported_topic_removes_it_from_lists_and_search(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'nutrition']);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'hydration-summer',
            'title' => 'Summer hydration tips',
        ]);
        $report = ContentReport::factory()->about($topic)->create();

        $this->getJson('/api/v1/forum/topics?q=hydration')->assertJsonCount(1, 'data');

        $report->hideContent(User::factory()->moderator()->create(), 'Рекламна содржина.');

        $this->assertSame(ForumContentStatus::Rejected, $topic->fresh()->status);
        $this->assertSame('Рекламна содржина.', $topic->fresh()->rejection_note);
        $this->getJson('/api/v1/forum/topics?q=hydration')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/forum/categories/nutrition/topics')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/forum/topics/recent')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/forum/categories/nutrition/topics/hydration-summer')->assertNotFound();
    }

    public function test_hiding_a_reported_reply_removes_it_and_corrects_the_reply_count(): void
    {
        $category = ForumCategory::factory()->create(['slug' => 'nutrition']);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'slug' => 'water']);
        $kept = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $hidden = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $this->assertSame(2, $topic->fresh()->replies_count);

        ContentReport::factory()->about($hidden)->create()->hideContent(User::factory()->moderator()->create());

        $this->assertSame(ForumContentStatus::Rejected, $hidden->fresh()->status);
        $this->assertSame(1, $topic->fresh()->replies_count);
        $this->getJson('/api/v1/forum/categories/nutrition/topics/water')
            ->assertOk()
            ->assertJsonCount(1, 'data.posts')
            ->assertJsonPath('data.posts.0.id', $kept->id);
    }

    public function test_two_moderators_hiding_the_same_reply_count_it_once(): void
    {
        Mail::fake();
        $category = ForumCategory::factory()->create();
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $reply = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        // Two queue rows loaded side by side, each still seeing the reply as published.
        $first = ContentReport::factory()->about($reply)->create()->load('reportable');
        $second = ContentReport::factory()->about($reply)->create()->load('reportable');

        $first->hideContent(User::factory()->moderator()->create());
        $second->hideContent(User::factory()->moderator()->create());

        $this->assertSame(1, $topic->fresh()->replies_count);
        Mail::assertQueued(UgcRejectedMail::class, 1);
    }

    public function test_keeping_reported_content_leaves_it_published(): void
    {
        $review = $this->approvedReview();
        $report = ContentReport::factory()->about($review)->create();
        $other = ContentReport::factory()->about($this->approvedReview(Doctor::factory()->create()))->create();
        $moderator = User::factory()->moderator()->create();

        $report->keepContent($moderator);

        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
        $this->assertSame(ReportStatus::Kept, $report->fresh()->status);
        $this->assertSame($moderator->id, $report->fresh()->resolved_by_id);
        $this->assertSame(ReportStatus::Open, $other->fresh()->status);
    }

    public function test_reporters_are_told_the_content_was_removed_without_the_moderators_name(): void
    {
        Mail::fake();
        $doctor = Doctor::factory()->create(['full_name' => 'д-р Ана Петровска']);
        $review = $this->approvedReview($doctor);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $deleted = User::factory()->create();
        ContentReport::factory()->about($review)->create(['user_id' => $first->id]);
        ContentReport::factory()->about($review)->create(['user_id' => $second->id]);
        ContentReport::factory()->about($review)->create(['user_id' => $deleted->id]);
        app(AnonymiseUser::class)->handle($deleted);
        $moderator = User::factory()->moderator()->create(['name' => 'Модератор Марко']);

        ContentReport::query()->firstOrFail()->hideContent($moderator);

        Mail::assertQueued(ContentReportOutcomeMail::class, 2);
        foreach ([$first, $second] as $reporter) {
            Mail::assertQueued(ContentReportOutcomeMail::class, function (ContentReportOutcomeMail $mail) use ($reporter): bool {
                $html = self::text($mail->render());

                return $mail->hasTo($reporter->email)
                    && $mail->removed
                    && str_contains($html, 'пријава во врска со рецензијата за „д-р Ана Петровска“')
                    && str_contains($html, 'Содржината е отстранета')
                    && ! str_contains($html, 'Марко');
            });
        }
        Mail::assertNotQueued(ContentReportOutcomeMail::class, fn (ContentReportOutcomeMail $mail): bool => $mail->hasTo($review->user->email));
    }

    public function test_a_reporter_is_told_the_content_stays_up(): void
    {
        Mail::fake();
        $category = ForumCategory::factory()->create();
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id, 'title' => 'Сон']);
        $reply = ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $reporter = User::factory()->create();
        $report = ContentReport::factory()->about($reply)->create(['user_id' => $reporter->id]);

        $report->keepContent(User::factory()->moderator()->create());

        Mail::assertQueued(ContentReportOutcomeMail::class, function (ContentReportOutcomeMail $mail) use ($reporter): bool {
            $html = self::text($mail->render());

            return $mail->hasTo($reporter->email)
                && ! $mail->removed
                && str_contains($html, 'пријава во врска со одговорот во темата „Сон“')
                && str_contains($html, 'останува објавена');
        });
        Mail::assertNotQueued(UgcRejectedMail::class);
    }

    private static function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
    }
}
