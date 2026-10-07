<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Enums\ReviewStatus;
use App\Mail\ImpactDigestMail;
use App\Mail\ReviewHelpfulMail;
use App\Mail\ReviewReminderMail;
use App\Mail\ReviewReplyMail;
use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Models\Doctor;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\Review;
use App\Models\ReviewReminder;
use App\Models\User;
use App\Support\Notifications\UnsubscribeToken;
use App\Support\UgcMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * W8-B „Известувања“ settings and the signed one-click unsubscribe (G4).
 */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_on_for_own_content_and_off_for_the_digest(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertExactJson(['data' => [
                'email_enabled' => true,
                'types' => ['moderation' => true, 'review_reply' => true, 'review_helpful' => true, 'impact_digest' => false],
                'digest_invited_at' => null,
            ]]);
    }

    public function test_a_member_switches_types_and_everything_off(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/notification-preferences', ['types' => ['impact_digest' => true, 'review_helpful' => false]])
            ->assertOk()
            ->assertJsonPath('data.types.impact_digest', true)
            ->assertJsonPath('data.types.review_helpful', false);

        $this->putJson('/api/v1/me/notification-preferences', ['email_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.email_enabled', false);

        $preferences = NotificationPreference::for($user);
        $this->assertFalse($preferences->allowsEmail(NotificationType::Moderation));
        $this->assertFalse($preferences->allowsEmail(NotificationType::ReviewReminder));

        // Unknown switches are refused rather than silently ignored.
        $this->putJson('/api/v1/me/notification-preferences', ['types' => ['marketing' => true]])
            ->assertUnprocessable();
    }

    public function test_the_list_shows_only_the_members_own_lines_and_marks_them_read(): void
    {
        $user = User::factory()->create();
        MemberNotification::query()->create(['user_id' => $user->id, 'type' => 'review_reply', 'data' => ['profile' => null]]);
        MemberNotification::query()->create(['user_id' => User::factory()->create()->id, 'type' => 'review_reply', 'data' => []]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.read', false);

        $this->postJson('/api/v1/me/notifications/read')->assertOk()->assertJsonPath('data.marked', 1);
        $this->getJson('/api/v1/me/notifications')->assertJsonPath('meta.unread_count', 0);
    }

    public function test_signed_out_requests_are_refused(): void
    {
        $this->getJson('/api/v1/me/notifications')->assertUnauthorized();
        $this->putJson('/api/v1/me/notification-preferences', [])->assertUnauthorized();
    }

    public function test_one_click_unsubscribe_turns_that_type_off_without_signing_in(): void
    {
        $user = User::factory()->create();
        $token = UnsubscribeToken::make($user, NotificationType::ReviewHelpful);

        $this->getJson('/api/v1/notifications/unsubscribe?token='.urlencode($token))
            ->assertOk()
            ->assertJsonPath('data.type', 'review_helpful');
        $this->assertTrue(NotificationPreference::for($user)->review_helpful, 'Looking at the link must not unsubscribe.');

        $this->postJson('/api/v1/notifications/unsubscribe', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.unsubscribed', true);

        $preferences = NotificationPreference::for($user);
        $this->assertFalse($preferences->review_helpful);
        $this->assertTrue($preferences->review_reply);
        $this->assertTrue($preferences->email_enabled);
    }

    public function test_a_forged_or_altered_token_is_refused(): void
    {
        $victim = User::factory()->create();
        $other = User::factory()->create();
        $token = UnsubscribeToken::make($other, NotificationType::Moderation);
        [, , $signature] = explode('.', $token);

        foreach ([
            $victim->id.'.moderation.'.$signature,
            $other->id.'.review_reply.'.$signature,
            $other->id.'.digest_invite.'.$signature,
            'garbage',
        ] as $forged) {
            $this->postJson('/api/v1/notifications/unsubscribe', ['token' => $forged])
                ->assertNotFound()
                ->assertJsonPath('code', 'notifications.unsubscribe_invalid');
        }

        $this->assertTrue(NotificationPreference::for($victim)->moderation);
    }

    public function test_the_reminder_link_cancels_every_pending_reminder(): void
    {
        $user = User::factory()->create();
        foreach (Doctor::factory()->count(2)->create() as $doctor) {
            ReviewReminder::query()->create(['user_id' => $user->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $doctor->id, 'remind_at' => now()->addDays(3)]);
        }

        $this->postJson('/api/v1/notifications/unsubscribe', ['token' => UnsubscribeToken::make($user, NotificationType::ReviewReminder)])
            ->assertOk();

        $this->assertDatabaseCount('review_reminders', 0);
    }

    public function test_every_new_member_mail_renders_its_unsubscribe_line(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();

        $mails = [
            [new ReviewReplyMail('Ана', 'д-р Ана Петровска', true, 'Ви благодариме.', 'https://example.test/doctors/a#reviews'), NotificationType::ReviewReply, 'Исклучи ги со еден клик'],
            [new ReviewHelpfulMail('Ана', 'д-р Ана Петровска', 2, 5, 'https://example.test/account/reviews'), NotificationType::ReviewHelpful, 'Исклучи ги со еден клик'],
            [new ReviewReminderMail('Ана', 'Клиника Здравје', false, 'https://example.test/facilities/k#review-form'), NotificationType::ReviewReminder, 'Откажи ги сите потсетници'],
            [new ImpactDigestMail('Ана', 'септември 2026', ['review_views' => 3, 'helpful_votes' => 1, 'replies' => 0, 'forum_answers' => 0, 'forum_replies_received' => 0, 'review_views_total' => 9], 'https://example.test/account/reviews'), NotificationType::ImpactDigest, 'Исклучи го месечниот преглед'],
        ];

        foreach ($mails as [$mail, $type, $line]) {
            $html = (string) $mail->withUnsubscribe($user, $type)->render();

            $this->assertStringContainsString($line, $html);
            $this->assertStringContainsString('/unsubscribe?token='.rawurlencode(UnsubscribeToken::make($user, $type)), $html);
            $this->assertStringContainsString('/account/notifications', $html);
            $this->assertDoesNotMatchRegularExpression('/[йщъыьэюяё]/iu', strip_tags($html));
        }

        $this->assertStringContainsString('Дали сте биле во „Клиника Здравје“?', strip_tags((string) $mails[2][0]->render()));
        $this->assertStringContainsString('не дека секој ја прочитал', (string) $mails[3][0]->render());
    }

    /**
     * A refusal or removal is the statement of reasons (DSA Art. 17): it is
     * e-mailed even with every e-mail switched off, and carries no
     * unsubscribe link. „Published“ still follows the settings.
     */
    public function test_refusals_and_removals_are_always_emailed(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $preferences = NotificationPreference::for($user);
        $preferences->forceFill(['email_enabled' => false, 'moderation' => false])->save();
        $refused = Review::factory()->create(['user_id' => $user->id, 'status' => ReviewStatus::Rejected, 'rejection_note' => 'Лични податоци.']);
        $removed = Review::factory()->create(['user_id' => $user->id]);
        $published = Review::factory()->create(['user_id' => $user->id]);

        UgcMailer::notifyRejected($refused);
        UgcMailer::notifyRejected($removed, removed: true);
        UgcMailer::notifyApproved($published);

        Mail::assertQueued(UgcRejectedMail::class, 2);
        Mail::assertNotQueued(UgcApprovedMail::class);
        Mail::assertQueued(UgcRejectedMail::class, function (UgcRejectedMail $mail): bool {
            $html = (string) $mail->render();

            return ! str_contains($html, '/unsubscribe?token=') && ! str_contains($html, 'Исклучи ги со еден клик');
        });
        $this->assertSame(3, MemberNotification::query()->where('user_id', $user->id)->where('type', NotificationType::Moderation)->count());
    }

    /**
     * Rotating APP_KEY with APP_PREVIOUS_KEYS (infra/deploy.md) keeps every
     * link already e-mailed working; a key that is not listed does not.
     */
    public function test_links_signed_under_a_previous_app_key_still_work(): void
    {
        $user = User::factory()->create();
        $oldKey = (string) config('app.key');
        $token = UnsubscribeToken::make($user, NotificationType::ReviewHelpful);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => []]);
        $this->assertNull(UnsubscribeToken::parse($token));

        config(['app.previous_keys' => [$oldKey]]);
        $this->assertSame($user->id, UnsubscribeToken::parse($token)['user']->id ?? null);
        $this->postJson('/api/v1/notifications/unsubscribe', ['token' => $token])->assertOk();
        $this->assertFalse(NotificationPreference::for($user)->review_helpful);
    }

    public function test_a_deleted_accounts_link_no_longer_works(): void
    {
        $user = User::factory()->create();
        $token = UnsubscribeToken::make($user, NotificationType::Moderation);
        $user->forceFill(['anonymised_at' => now()])->save();

        $this->postJson('/api/v1/notifications/unsubscribe', ['token' => $token])->assertNotFound();
    }
}
