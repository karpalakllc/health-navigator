<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Mail\ReviewReminderMail;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\Review;
use App\Models\ReviewReminder;
use App\Models\User;
use App\Support\AccountExport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * W8-B „Потсети ме за 2 недели“: explicit, per profile, one e-mail, then the
 * row is gone; in the export, gone with the account.
 */
class ReviewRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->member = User::factory()->create();
        $this->doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'full_name' => 'д-р Ана Петровска']);
    }

    public function test_a_member_sets_a_reminder_for_two_weeks_and_can_cancel_it(): void
    {
        Sanctum::actingAs($this->member);

        $created = $this->postJson('/api/v1/me/review-reminders', ['kind' => 'doctor', 'slug' => 'ana-petrovska'])
            ->assertCreated()
            ->assertJsonPath('data.profile.name', 'д-р Ана Петровска')
            ->json('data');

        $reminder = ReviewReminder::query()->sole();
        $this->assertSame($this->member->id, $reminder->user_id);
        $this->assertTrue($reminder->remind_at->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()));

        // Asking again for the same profile moves the date, never a second row.
        $this->postJson('/api/v1/me/review-reminders', ['kind' => 'doctor', 'slug' => 'ana-petrovska'])->assertOk();
        $this->assertDatabaseCount('review_reminders', 1);

        $this->getJson('/api/v1/me/review-reminders')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/me/review-reminders/'.$created['id'])->assertOk();
        $this->assertDatabaseCount('review_reminders', 0);
    }

    public function test_a_member_cannot_cancel_someone_elses_reminder(): void
    {
        $reminder = ReviewReminder::query()->create(['user_id' => User::factory()->create()->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id, 'remind_at' => now()->addDay()]);
        Sanctum::actingAs($this->member);

        $this->deleteJson('/api/v1/me/review-reminders/'.$reminder->id)->assertOk();

        $this->assertDatabaseCount('review_reminders', 1);
    }

    public function test_no_reminder_for_own_profile_or_an_already_reviewed_one(): void
    {
        $this->doctor->forceFill(['owner_user_id' => $this->member->id])->save();
        $facility = Facility::factory()->create(['slug' => 'klinika']);
        Review::factory()->create(['user_id' => $this->member->id, 'reviewable_type' => Facility::class, 'reviewable_id' => $facility->id]);
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/me/review-reminders', ['kind' => 'doctor', 'slug' => 'ana-petrovska'])
            ->assertUnprocessable()->assertJsonValidationErrors('reminder');
        $this->postJson('/api/v1/me/review-reminders', ['kind' => 'facility', 'slug' => 'klinika'])
            ->assertUnprocessable()->assertJsonValidationErrors('reminder');
        $this->postJson('/api/v1/me/review-reminders', ['kind' => 'doctor', 'slug' => 'nema-takov'])->assertNotFound();
    }

    public function test_the_due_reminder_is_sent_once_and_deleted(): void
    {
        ReviewReminder::query()->create(['user_id' => $this->member->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id, 'remind_at' => now()->addDays(14)]);

        $this->artisan('reviews:send-reminders')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->travel(15)->days();
        $this->artisan('reviews:send-reminders')->assertSuccessful();

        Mail::assertQueued(ReviewReminderMail::class, fn (ReviewReminderMail $mail): bool => $mail->hasTo($this->member->email)
            && $mail->isDoctor
            && str_ends_with($mail->actionUrl, '/doctors/ana-petrovska#review-form')
            && $mail->unsubscribeType === 'review_reminder');
        $this->assertDatabaseCount('review_reminders', 0);
        $this->assertSame(1, MemberNotification::query()->where('type', 'review_reminder')->count());

        $this->artisan('reviews:send-reminders')->assertSuccessful();
        Mail::assertQueued(ReviewReminderMail::class, 1);
    }

    public function test_a_reminder_that_is_no_longer_needed_is_dropped_silently(): void
    {
        ReviewReminder::query()->create(['user_id' => $this->member->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id, 'remind_at' => now()->subMinute()]);
        Review::factory()->create(['user_id' => $this->member->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id]);

        $this->artisan('reviews:send-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('review_reminders', 0);
        $this->assertDatabaseCount('member_notifications', 0);
    }

    public function test_reminders_and_notifications_are_in_the_export_and_go_with_the_account(): void
    {
        ReviewReminder::query()->create(['user_id' => $this->member->id, 'reviewable_type' => Doctor::class, 'reviewable_id' => $this->doctor->id, 'remind_at' => now()->addDays(3)]);
        MemberNotification::query()->create(['user_id' => $this->member->id, 'type' => 'review_reply', 'data' => ['profile' => null]]);
        NotificationPreference::query()->create(['user_id' => $this->member->id, 'impact_digest' => true]);

        ob_start();
        (new AccountExport($this->member))->write();
        $export = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('д-р Ана Петровска', $export['review_reminders'][0]['about']['name']);
        $this->assertSame('review_reply', $export['notifications'][0]['type']);
        $this->assertTrue($export['notification_preferences']['types']['impact_digest']);

        app(AnonymiseUser::class)->handle($this->member);

        $this->assertDatabaseCount('review_reminders', 0);
        $this->assertDatabaseCount('member_notifications', 0);
        $this->assertDatabaseCount('notification_preferences', 0);
    }

    public function test_the_notification_jobs_are_scheduled(): void
    {
        $expressions = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn (Event $event): array => [(string) $event->command => $event->expression]);

        $find = fn (string $needle): ?string => $expressions->first(fn (string $expression, string $command): bool => str_contains($command, $needle));

        $this->assertSame('0 * * * *', $find('reviews:send-reminders'));
        $this->assertSame('0 18 * * *', $find('reviews:notify-helpful'));
        $this->assertSame('0 9 1 * *', $find('notifications:send-impact-digest'));
        $this->assertSame('55 4 * * *', $find('MemberNotification'));
    }

    public function test_old_in_app_notifications_are_pruned(): void
    {
        $old = MemberNotification::query()->create(['user_id' => $this->member->id, 'type' => 'review_reply', 'data' => []]);
        $old->forceFill(['created_at' => now()->subDays(MemberNotification::RETENTION_DAYS + 1)])->save();
        MemberNotification::query()->create(['user_id' => $this->member->id, 'type' => 'review_reply', 'data' => []]);

        $this->artisan('model:prune', ['--model' => [MemberNotification::class]])->assertSuccessful();

        $this->assertDatabaseCount('member_notifications', 1);
    }
}
