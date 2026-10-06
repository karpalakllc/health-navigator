<?php

namespace Tests\Feature\Console;

use App\Enums\ForumContentStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Mail\ModerationDigestMail;
use App\Models\ContentReport;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ModerationDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_digest_queues_mail_for_staff_moderator_with_pending_reviews(): void
    {
        Mail::fake();

        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');

        Review::factory()->create(['status' => ReviewStatus::Pending]);

        $this->artisan('moderation:send-digest')->assertSuccessful();

        Mail::assertQueued(ModerationDigestMail::class, function (ModerationDigestMail $mail) use ($moderator): bool {
            return $mail->hasTo($moderator->email) && $mail->totalPending >= 1;
        });
    }

    public function test_digest_skips_when_nothing_pending(): void
    {
        Mail::fake();

        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');

        $this->artisan('moderation:send-digest')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_digest_includes_forum_moderator_with_pending_topics(): void
    {
        Mail::fake();

        $moderator = User::factory()->create();
        $moderator->assignRole('Forum Moderator');

        ForumTopic::factory()->create(['status' => ForumContentStatus::Pending]);

        $this->artisan('moderation:send-digest')->assertSuccessful();

        Mail::assertQueued(ModerationDigestMail::class, function (ModerationDigestMail $mail) use ($moderator): bool {
            return $mail->hasTo($moderator->email);
        });
    }

    public function test_digest_counts_open_reports(): void
    {
        Mail::fake();

        $moderator = User::factory()->moderator()->create();
        $moderator->assignRole('Moderator');
        $forumModerator = User::factory()->create();
        $forumModerator->assignRole('Forum Moderator');

        ContentReport::factory()->count(2)->create();
        ContentReport::factory()->create(['status' => ReportStatus::Kept]);

        $this->artisan('moderation:send-digest')->assertSuccessful();

        Mail::assertQueued(ModerationDigestMail::class, function (ModerationDigestMail $mail) use ($moderator): bool {
            $reports = collect($mail->queues)->firstWhere('label', 'Отворени пријави');

            return $mail->hasTo($moderator->email)
                && $mail->totalPending === 2
                && $reports !== null
                && $reports['count'] === 2
                && str_contains($reports['url'], '/admin/content-reports');
        });
        Mail::assertNotQueued(ModerationDigestMail::class, fn (ModerationDigestMail $mail): bool => $mail->hasTo($forumModerator->email));
    }
}
