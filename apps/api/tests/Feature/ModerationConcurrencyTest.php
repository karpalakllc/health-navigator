<?php

namespace Tests\Feature;

use App\Enums\ForumContentStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Mail\ContentReportOutcomeMail;
use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Models\ContentReport;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Dual-moderator approve must not double-count replies or double-mail.
 * Keep vs hide on the same reported item must serialize on the content row.
 */
class ModerationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_two_approvals_of_the_same_pending_reply_count_once(): void
    {
        Mail::fake();

        $category = ForumCategory::factory()->create();
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);
        ForumPost::factory()->create(['forum_topic_id' => $topic->id]);
        $reply = ForumPost::factory()->pending()->create(['forum_topic_id' => $topic->id]);
        $before = $topic->fresh()->replies_count;

        $first = ForumPost::query()->findOrFail($reply->id);
        $second = ForumPost::query()->findOrFail($reply->id);
        $first->approve(User::factory()->moderator()->create());
        $second->approve(User::factory()->moderator()->create());

        $this->assertSame(ForumContentStatus::Approved, $reply->fresh()->status);
        $this->assertSame($before + 1, $topic->fresh()->replies_count);
        Mail::assertQueued(UgcApprovedMail::class, 1);
    }

    public function test_two_approvals_of_the_same_pending_review_mail_once(): void
    {
        Mail::fake();

        $review = Review::factory()->create(['status' => ReviewStatus::Pending]);
        $first = Review::query()->findOrFail($review->id);
        $second = Review::query()->findOrFail($review->id);

        $first->approve(User::factory()->moderator()->create());
        $second->approve(User::factory()->moderator()->create());

        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
        Mail::assertQueued(UgcApprovedMail::class, 1);
    }

    public function test_keep_after_hide_does_not_mark_reports_kept(): void
    {
        Mail::fake();

        $review = Review::factory()->approved()->create();
        // Two queue rows for the same item, as two moderators would hold them.
        $keep = ContentReport::factory()->about($review)->create()->load('reportable');
        $hide = ContentReport::factory()->about($review)->create()->load('reportable');

        $hide->hideContent(User::factory()->moderator()->create());
        $keep->keepContent(User::factory()->moderator()->create());

        $this->assertSame(ReviewStatus::Rejected, $review->fresh()->status);
        $this->assertSame(ReportStatus::Hidden, $keep->fresh()->status);
        $this->assertSame(ReportStatus::Hidden, $hide->fresh()->status);
        Mail::assertQueued(ContentReportOutcomeMail::class, 2);
        Mail::assertQueued(ContentReportOutcomeMail::class, fn (ContentReportOutcomeMail $mail): bool => $mail->removed);
        Mail::assertQueued(UgcRejectedMail::class, 1);
    }

    public function test_hide_after_keep_does_not_unpublish_kept_content(): void
    {
        Mail::fake();

        $review = Review::factory()->approved()->create();
        $keep = ContentReport::factory()->about($review)->create()->load('reportable');
        $hide = ContentReport::factory()->about($review)->create()->load('reportable');

        $keep->keepContent(User::factory()->moderator()->create());
        $hide->hideContent(User::factory()->moderator()->create());

        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
        $this->assertSame(ReportStatus::Kept, $keep->fresh()->status);
        $this->assertSame(ReportStatus::Kept, $hide->fresh()->status);
        Mail::assertQueued(ContentReportOutcomeMail::class, 2);
        Mail::assertQueued(ContentReportOutcomeMail::class, fn (ContentReportOutcomeMail $mail): bool => ! $mail->removed);
        Mail::assertNotQueued(UgcRejectedMail::class);
    }
}
