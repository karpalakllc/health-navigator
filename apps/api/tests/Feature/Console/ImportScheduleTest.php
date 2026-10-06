<?php

namespace Tests\Feature\Console;

use App\Events\ImportRunFinished;
use App\Mail\ImportAlertMail;
use App\Support\DataOps\ImportAlerter;
use App\Support\DataOps\ImportSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * The source imports run on a schedule only once the owner turns them on,
 * only while their command is installed, and a failed or unusually large run
 * reaches a person (docs/data-import.md).
 */
class ImportScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Mail::fake();
    }

    private function scheduled(string $command): Event
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_ends_with((string) $event->command, $command))
            ->values();

        $this->assertCount(1, $events, $command);

        return $events[0];
    }

    public function test_fzom_is_weekly_and_komora_monthly(): void
    {
        $this->assertSame('import:fzom', ImportSchedule::command('fzom'));
        $this->assertSame('import:komora-licences', ImportSchedule::command('komora'));

        $this->assertSame('30 5 * * 1', $this->scheduled('import:fzom')->expression);
        $this->assertSame('0 6 3 * *', $this->scheduled('import:komora-licences')->expression);
    }

    public function test_both_are_off_by_default(): void
    {
        Artisan::command('import:fzom', fn () => 0);
        Artisan::command('import:komora-licences', fn () => 0);

        $this->assertFalse(config('data_ops.schedule.fzom.enabled'));
        $this->assertFalse(config('data_ops.schedule.komora.enabled'));
        $this->assertFalse($this->scheduled('import:fzom')->filtersPass($this->app));
        $this->assertFalse($this->scheduled('import:komora-licences')->filtersPass($this->app));
    }

    public function test_an_enabled_import_runs_only_while_its_command_is_installed(): void
    {
        Artisan::command('import:w6c-installed', fn () => 0);
        config(['data_ops.schedule.fzom.enabled' => true]);
        config(['data_ops.schedule.fzom.command' => 'import:w6c-missing-command']);

        $this->assertFalse(ImportSchedule::shouldRun('fzom'));

        config(['data_ops.schedule.fzom.command' => 'import:w6c-installed']);

        $this->assertTrue(ImportSchedule::shouldRun('fzom'));
    }

    public function test_a_failed_scheduled_run_mails_the_import_inbox_once_per_window(): void
    {
        config(['data_ops.alerts.email' => 'uvoz@example.test']);

        app(ImportAlerter::class)->scheduledRunFailed('fzom', 'import:fzom');
        app(ImportAlerter::class)->scheduledRunFailed('fzom', 'import:fzom');

        Mail::assertSentCount(1);
        Mail::assertSent(ImportAlertMail::class, fn (ImportAlertMail $mail): bool => $mail->hasTo('uvoz@example.test')
            && str_contains($mail->envelope()->subject, 'Неуспешен увоз')
            && str_contains($mail->envelope()->subject, 'ФЗОМ'));
    }

    public function test_the_scheduler_failure_hook_reaches_the_alerter(): void
    {
        config(['data_ops.alerts.email' => null, 'zdravje.alerts.email' => 'ops@example.test']);

        $event = $this->scheduled('import:komora-licences');
        $event->exitCode = 1;
        $event->finish($this->app, 1);

        Mail::assertSent(ImportAlertMail::class, fn (ImportAlertMail $mail): bool => $mail->hasTo('ops@example.test')
            && str_contains($mail->render(), 'import:komora-licences'));
    }

    public function test_a_finished_run_alerts_on_failure_and_on_a_large_diff_only(): void
    {
        config(['data_ops.alerts.email' => 'uvoz@example.test', 'data_ops.alerts.large_diff_ratio' => 0.10, 'data_ops.alerts.large_diff_min' => 25]);

        // A normal weekly run: a handful of changes.
        ImportRunFinished::dispatch('fzom', true, seen: 5000, created: 3, updated: 40, missing: 2);
        Mail::assertNothingSent();

        // Too small to matter even as a share (a test file).
        ImportRunFinished::dispatch('fzom', true, seen: 10, created: 10);
        Mail::assertNothingSent();

        // A truncated download: most records "missing".
        ImportRunFinished::dispatch('fzom', true, seen: 800, updated: 10, missing: 4200, reviewUrl: 'https://admin.example.test/imports/7');
        Mail::assertSent(ImportAlertMail::class, fn (ImportAlertMail $mail): bool => str_contains($mail->envelope()->subject, 'Голема промена')
            && str_contains($mail->render(), '4200')
            && str_contains($mail->render(), 'https://admin.example.test/imports/7'));

        ImportRunFinished::dispatch('komora', false, error: "Parse error on page 3 for user@example.test\nstack…");
        Mail::assertSent(ImportAlertMail::class, function (ImportAlertMail $mail): bool {
            $html = $mail->render();

            return str_contains($mail->envelope()->subject, 'Лекарска комора')
                && str_contains($html, 'Parse error on page 3')
                && ! str_contains($html, 'user@example.test')
                && ! str_contains($html, 'stack');
        });

        Mail::assertSentCount(2);
    }

    public function test_nothing_is_sent_without_an_alert_inbox_and_a_mail_failure_does_not_throw(): void
    {
        config(['data_ops.alerts.email' => null, 'zdravje.alerts.email' => null]);
        $this->assertFalse(app(ImportAlerter::class)->send('fzom', ImportAlerter::KIND_FAILED));
        Mail::assertNothingSent();

        config(['data_ops.alerts.email' => 'uvoz@example.test']);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->assertFalse(app(ImportAlerter::class)->send('komora', ImportAlerter::KIND_FAILED));
    }
}
