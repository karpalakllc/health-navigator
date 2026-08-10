<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Mail\UgcApprovedMail;
use App\Mail\UgcRejectedMail;
use App\Mail\UgcSubmittedMail;
use App\Mail\WelcomeMail;
use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\FrontendUrl;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionalMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
    }

    /**
     * The welcome mail moved from signup to verification: registration only
     * promises that a message was sent, and the account is not usable until the
     * address is confirmed, so welcoming someone at signup would be premature.
     */
    public function test_verifying_an_address_queues_the_welcome_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Ana Member',
            'email' => 'ana@example.com',
            'email_verified_at' => null,
        ]);

        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]))->assertRedirectContains('status=verified');

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->recipientName === 'Ana Member'
                && $mail->loginUrl === FrontendUrl::to('/login');
        });
    }

    public function test_registration_alone_does_not_queue_the_welcome_mail(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Member',
            'email' => 'ana@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ])->assertStatus(202);

        Mail::assertNotQueued(WelcomeMail::class);
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_review_approval_queues_ugc_mail(): void
    {
        Mail::fake();

        $doctor = Doctor::factory()->create(['slug' => 'dr-ana']);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $review = Review::factory()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'status' => ReviewStatus::Pending,
        ]);

        $review->approve($moderator);

        Mail::assertQueued(UgcApprovedMail::class, function (UgcApprovedMail $mail) use ($doctor): bool {
            return str_contains($mail->actionUrl, "/doctors/{$doctor->slug}");
        });
    }

    public function test_forum_topic_approval_queues_ugc_mail(): void
    {
        Mail::fake();

        $category = ForumCategory::factory()->create(['slug' => 'general']);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $topic = ForumTopic::factory()->pending()->create([
            'forum_category_id' => $category->id,
            'user_id' => $member->id,
            'slug' => 'help-topic',
            'title' => 'Help topic',
        ]);

        $topic->approve($moderator);

        Mail::assertQueued(UgcApprovedMail::class, function (UgcApprovedMail $mail): bool {
            return str_contains($mail->actionUrl, '/forum/general/help-topic');
        });
    }

    public function test_review_submission_queues_submitted_mail(): void
    {
        Mail::fake();

        $doctor = Doctor::factory()->create(['slug' => 'dr-ana']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/doctors/dr-ana/reviews', [
            'rating' => 5,
            'body' => 'Excellent care and clear communication throughout.',
        ])->assertCreated();

        Mail::assertQueued(UgcSubmittedMail::class, function (UgcSubmittedMail $mail): bool {
            return $mail->actionUrl === FrontendUrl::to('/account/reviews');
        });
    }

    public function test_review_rejection_queues_rejected_mail(): void
    {
        Mail::fake();

        $doctor = Doctor::factory()->create(['slug' => 'dr-ana']);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $review = Review::factory()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            'status' => ReviewStatus::Pending,
        ]);

        $review->reject($moderator, 'Does not meet community guidelines.');

        Mail::assertQueued(UgcRejectedMail::class, function (UgcRejectedMail $mail): bool {
            return $mail->rejectionNote === 'Does not meet community guidelines.'
                && $mail->actionUrl === FrontendUrl::to('/account/reviews');
        });
    }
}
