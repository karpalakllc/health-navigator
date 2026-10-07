<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Mail\ReviewHelpfulMail;
use App\Mail\ReviewReplyMail;
use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Models\Doctor;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\Review;
use App\Models\User;
use App\Support\ReviewHelpfulVotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * W8-B: the author of a published review hears about a public reply (once),
 * new „Корисно“ votes (one daily batch, never who voted) and moderation
 * decisions — in „Известувања“ always, by e-mail as their settings allow.
 */
class ReviewNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'full_name' => 'д-р Ана Петровска']);
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

    public function test_a_staff_reply_tells_the_author_once_with_an_unsubscribe_link(): void
    {
        $review = $this->review();
        $staff = User::factory()->admin()->create();

        $review->respond($staff, 'Ви благодариме за повратната информација.');

        Mail::assertQueued(ReviewReplyMail::class, function (ReviewReplyMail $mail): bool {
            $headers = $mail->headers()->text;

            return $mail->hasTo($this->author->email)
                && $mail->profileName === 'д-р Ана Петровска'
                && ! $mail->fromDoctor
                && str_contains((string) $mail->unsubscribeUrl, '/unsubscribe?token=')
                && str_starts_with($headers['List-Unsubscribe'] ?? '', '<')
                && ($headers['List-Unsubscribe-Post'] ?? null) === 'List-Unsubscribe=One-Click';
        });
        $this->assertSame(1, MemberNotification::query()->where('user_id', $this->author->id)->where('type', 'review_reply')->count());

        // Editing the reply is not news again.
        $review->fresh()->respond($staff, 'Исправен одговор.');
        Mail::assertQueuedCount(1);
    }

    public function test_a_doctor_reply_is_announced_only_once_staff_approve_it(): void
    {
        $review = $this->review();
        $doctorAccount = User::factory()->create();
        $staff = User::factory()->admin()->create();

        $review->replyAsDoctor($doctorAccount, 'Одговор од лекарот.', requiresModeration: true);
        Mail::assertNotQueued(ReviewReplyMail::class);

        $review->fresh()->approveDoctorReply($staff);
        Mail::assertQueued(ReviewReplyMail::class, fn (ReviewReplyMail $mail): bool => $mail->fromDoctor);
    }

    public function test_removing_a_reply_rearms_the_notice(): void
    {
        $review = $this->review();
        $staff = User::factory()->admin()->create();

        $review->respond($staff, 'Прв одговор.');
        $review->fresh()->removeResponse();
        $review->fresh()->respond($staff, 'Нов одговор.');

        Mail::assertQueued(ReviewReplyMail::class, 2);
    }

    public function test_reply_e_mail_respects_the_switch_but_the_in_app_line_stays(): void
    {
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'review_reply' => false]);

        $this->review()->respond(User::factory()->admin()->create(), 'Одговор.');

        Mail::assertNotQueued(ReviewReplyMail::class);
        $this->assertDatabaseHas('member_notifications', ['user_id' => $this->author->id, 'type' => 'review_reply']);
    }

    public function test_helpful_votes_are_batched_and_never_announced_twice(): void
    {
        $review = $this->review();
        $voters = User::factory()->count(3)->create();

        ReviewHelpfulVotes::add($review, $voters[0]);
        ReviewHelpfulVotes::add($review, $voters[1]);

        $this->artisan('reviews:notify-helpful')->assertSuccessful();

        Mail::assertQueued(ReviewHelpfulMail::class, 1);
        Mail::assertQueued(ReviewHelpfulMail::class, fn (ReviewHelpfulMail $mail): bool => $mail->newVotes === 2 && $mail->totalVotes === 2);
        $line = MemberNotification::query()->where('type', 'review_helpful')->sole();
        $this->assertSame(2, $line->data['new_votes']);
        $this->assertArrayNotHasKey('voters', $line->data);

        // Nothing new: nothing sent. A vote taken back and given again is not news.
        $this->artisan('reviews:notify-helpful')->assertSuccessful();
        ReviewHelpfulVotes::remove($review, $voters[1]);
        ReviewHelpfulVotes::add($review, $voters[1]);
        $this->artisan('reviews:notify-helpful')->assertSuccessful();
        Mail::assertQueued(ReviewHelpfulMail::class, 1);

        ReviewHelpfulVotes::add($review, $voters[2]);
        $this->artisan('reviews:notify-helpful')->assertSuccessful();
        Mail::assertQueued(ReviewHelpfulMail::class, fn (ReviewHelpfulMail $mail): bool => $mail->newVotes === 1 && $mail->totalVotes === 3);
    }

    public function test_a_deleted_author_gets_no_helpful_notice(): void
    {
        $review = $this->review();
        $this->author->forceFill(['anonymised_at' => now()])->save();
        ReviewHelpfulVotes::add($review, User::factory()->create());

        $this->artisan('reviews:notify-helpful')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(1, $review->fresh()->helpful_notified_count);
    }

    public function test_moderation_decisions_land_in_the_account_and_mail_carries_the_unsubscribe_link(): void
    {
        $moderator = User::factory()->moderator()->create();
        $review = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);

        $review->approve($moderator);

        Mail::assertQueued(UgcApprovedMail::class, fn (UgcApprovedMail $mail): bool => $mail->unsubscribeType === NotificationType::Moderation->value
            && isset($mail->headers()->text['List-Unsubscribe']));
        $line = MemberNotification::query()->where('user_id', $this->author->id)->where('type', 'moderation')->sole();
        $this->assertSame(['event' => 'published', 'content' => 'review', 'title' => 'д-р Ана Петровска', 'path' => '/doctors/ana-petrovska'], $line->data);
    }

    /**
     * The switch silences „published“; a refusal is the statement of
     * reasons and is e-mailed regardless (docs/notice-and-action.md).
     */
    public function test_moderation_e_mails_can_be_switched_off_except_the_statement_of_reasons(): void
    {
        NotificationPreference::query()->create(['user_id' => $this->author->id, 'moderation' => false]);
        $moderator = User::factory()->moderator()->create();
        $published = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);
        $refused = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => Doctor::factory()->create()->id]);

        $published->approve($moderator);
        $refused->reject($moderator, 'Лични податоци.');

        Mail::assertNotQueued(UgcApprovedMail::class);
        Mail::assertQueued(UgcRejectedMail::class, fn (UgcRejectedMail $mail): bool => ! isset($mail->headers()->text['List-Unsubscribe']));
        $this->assertSame(2, MemberNotification::query()->where('user_id', $this->author->id)->where('type', 'moderation')->count());
    }

    public function test_the_first_published_review_invites_to_the_digest_once_and_only_in_the_account(): void
    {
        $moderator = User::factory()->moderator()->create();
        $first = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);
        $second = Review::factory()->create(['user_id' => $this->author->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => Doctor::factory()->create()->id]);

        $first->approve($moderator);
        $second->approve($moderator);

        $this->assertSame(1, MemberNotification::query()->where('type', 'digest_invite')->count());
        $preferences = NotificationPreference::for($this->author);
        $this->assertNotNull($preferences->digest_invited_at);
        $this->assertFalse($preferences->impact_digest);
    }
}
