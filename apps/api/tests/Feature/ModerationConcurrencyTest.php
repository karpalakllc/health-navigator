<?php

namespace Tests\Feature;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Mail\UgcApprovedMail;
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
}
