<?php

namespace Tests\Feature\Console;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserKind;
use App\Mail\NewContentReportsMail;
use App\Models\ContentReport;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The terms promise reports are reviewed (goal: within 24 hours); this is
 * what tells staff a report has arrived without anyone watching the panel.
 */
class ContentReportAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Mail::fake();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_one_summary_per_run_goes_to_staff_who_can_see_the_queue_and_the_shared_inbox(): void
    {
        config(['zdravje.reports.alert_email' => 'reports@example.test']);
        $moderator = $this->staff(RoleCatalog::MODERATOR);
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $forumModerator = User::factory()->create();
        $forumModerator->assignRole(RoleCatalog::FORUM_MODERATOR);
        $suspended = $this->staff(RoleCatalog::MODERATOR);
        $suspended->forceFill(['suspended_at' => now()])->save();

        $reporter = User::factory()->create(['name' => 'Стојан Пријавувач', 'email' => 'stojan@example.test']);
        ContentReport::factory()->count(2)->create(['user_id' => $reporter->id, 'reason' => ReportReason::Abuse, 'note' => 'Лична белешка']);
        ContentReport::factory()->create(['reason' => ReportReason::Spam]);
        $alreadyResolved = ContentReport::factory()->create(['status' => ReportStatus::Kept]);

        $this->artisan('reports:alert-staff')->assertSuccessful();

        Mail::assertQueuedCount(3);
        foreach ([$moderator->email, $admin->email, 'reports@example.test'] as $address) {
            Mail::assertQueued(NewContentReportsMail::class, fn (NewContentReportsMail $mail): bool => $mail->hasTo($address));
        }
        Mail::assertNotQueued(NewContentReportsMail::class, fn (NewContentReportsMail $mail): bool => $mail->hasTo($forumModerator->email) || $mail->hasTo($suspended->email));

        Mail::assertQueued(NewContentReportsMail::class, function (NewContentReportsMail $mail): bool {
            $html = $mail->render();
            $subject = $mail->envelope()->subject;

            return $mail->newReports === 3
                && $mail->openReports === 3
                && str_contains($html, 'Навреда или вознемирување')
                && ! str_contains($subject.$html, 'Стојан')
                && ! str_contains($subject.$html, 'stojan@example.test')
                && ! str_contains($html, 'Лична белешка');
        });

        $this->assertNull($alreadyResolved->fresh()->staff_alerted_at);
        $this->assertSame(0, ContentReport::query()->open()->whereNull('staff_alerted_at')->count());
    }

    public function test_reports_are_announced_once_and_a_quiet_queue_sends_nothing(): void
    {
        $this->staff(RoleCatalog::MODERATOR);
        ContentReport::factory()->create();

        $this->artisan('reports:alert-staff')->assertSuccessful();
        Mail::assertQueuedCount(1);

        $this->artisan('reports:alert-staff')->assertSuccessful();
        Mail::assertQueuedCount(1);

        ContentReport::factory()->create();
        $this->artisan('reports:alert-staff')->assertSuccessful();

        Mail::assertQueuedCount(2);
        Mail::assertQueued(NewContentReportsMail::class, fn (NewContentReportsMail $mail): bool => $mail->newReports === 1 && $mail->openReports === 2);
    }

    public function test_it_runs_every_ten_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'reports:alert-staff'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('*/10 * * * *', $events[0]->expression);
    }
}
