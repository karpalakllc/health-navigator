<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

/**
 * A queued job that fails for good mails PLATFORM_ALERT_EMAIL once per job
 * class per throttle window (App\Listeners\NotifyOnFailedJob).
 */
class FailedJobAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'mail.default' => 'array',
            'zdravje.alerts.email' => 'alerts@zdravje360.test',
            'zdravje.alerts.failed_job_throttle_minutes' => 15,
        ]);
    }

    public function test_a_failing_queued_mail_sends_one_alert(): void
    {
        $this->failAQueuedMail(new AlertProbeFailingMail);

        $this->assertSame(1, DB::table('failed_jobs')->count());

        $alerts = $this->alerts();
        $this->assertCount(1, $alerts);

        $email = $alerts->first()->getOriginalMessage();
        $this->assertSame('alerts@zdravje360.test', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Queued job failed: AlertProbeFailingMail', (string) $email->getSubject());
        $this->assertStringContainsString(AlertProbeFailingMail::class, (string) $email->getTextBody());
        $this->assertStringContainsString('smtp is down', (string) $email->getTextBody());
        // The queued mail's own recipient never reaches the alert.
        $this->assertStringNotContainsString('member@example.test', (string) $email->getTextBody());
    }

    public function test_a_storm_of_failures_sends_one_alert_per_job_class_per_window(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->failAQueuedMail(new AlertProbeFailingMail);
        }
        $this->failAQueuedMail(new AlertProbeOtherFailingMail);

        $this->assertSame(4, DB::table('failed_jobs')->count());
        $this->assertCount(2, $this->alerts());

        $this->travel(16)->minutes();
        $this->failAQueuedMail(new AlertProbeFailingMail);

        $this->assertCount(3, $this->alerts());
    }

    public function test_nothing_is_sent_without_an_alert_address(): void
    {
        config(['zdravje.alerts.email' => null]);

        $this->failAQueuedMail(new AlertProbeFailingMail);

        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertCount(0, $this->alerts());
    }

    public function test_an_alert_that_cannot_be_sent_does_not_break_the_worker(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('mail server down too'));

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\Probe');
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('uuid')->andReturn('00000000-0000-0000-0000-000000000000');

        event(new JobFailed('database', $job, new RuntimeException('boom')));

        // Reaching here without an exception is the assertion.
        $this->addToAssertionCount(1);
    }

    private function failAQueuedMail(Mailable $mail): void
    {
        Mail::to('member@example.test')->queue($mail);

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true, '--tries' => 1])
            ->assertSuccessful();
    }

    /** @return Collection<int, SentMessage> */
    private function alerts(): Collection
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()
            ->filter(fn (SentMessage $message): bool => str_contains((string) $message->getOriginalMessage()->getSubject(), 'Queued job failed'))
            ->values();
    }
}

class AlertProbeFailingMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        throw new RuntimeException("smtp is down\nsecond line with details");
    }
}

class AlertProbeOtherFailingMail extends AlertProbeFailingMail {}
