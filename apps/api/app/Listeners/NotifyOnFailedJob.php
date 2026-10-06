<?php

namespace App\Listeners;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Mail\Message;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mails PLATFORM_ALERT_EMAIL when a queued job fails for good (out of tries).
 *
 * Every sign-up verification, password reset and moderation notice is a queued
 * job (N1), so a dead mail server or a broken worker otherwise shows up only
 * as users who never get their email. Sentry still captures the exception;
 * this is the "someone look at failed_jobs" signal for a deployment without a
 * Sentry DSN or anyone watching it.
 *
 * - Sent with Mail::raw on the default mailer, synchronously: queueing the
 *   alert would put it behind the very worker that is failing.
 * - Throttled to one mail per job class per `failed_job_throttle_minutes`
 *   (Cache::add is atomic on the shared cache store), so a storm of failures
 *   sends one alert, not hundreds.
 * - Never throws. When the alert itself cannot be sent (often the same broken
 *   mail server), it is logged and the worker carries on.
 * - Carries no job payload (no mail body, no recipient): just the job class,
 *   queue, the exception class and the first line of its message, truncated
 *   (an SMTP refusal can still name an address there), plus the request ID
 *   the job was dispatched under.
 *
 * Registered by listener discovery (app/Listeners); do not also
 * Event::listen() it.
 */
class NotifyOnFailedJob
{
    private const MESSAGE_LIMIT = 300;

    public function handle(JobFailed $event): void
    {
        $recipient = config('zdravje.alerts.email');

        if (! is_string($recipient) || trim($recipient) === '') {
            return;
        }

        $jobClass = $event->job->resolveName();
        $minutes = max(1, (int) config('zdravje.alerts.failed_job_throttle_minutes', 15));

        if (! Cache::add('alerts:failed-job:'.sha1($jobClass), true, now()->addMinutes($minutes))) {
            return;
        }

        try {
            Mail::raw($this->body($event, $jobClass, $minutes), function (Message $message) use ($recipient, $jobClass): void {
                $message->to(trim($recipient))
                    ->subject('['.config('app.name').'] Queued job failed: '.class_basename($jobClass));
            });
        } catch (Throwable $e) {
            Log::warning('Could not send the failed-job alert.', [
                'job' => $jobClass,
                'alert_error' => $e::class,
            ]);
        }
    }

    private function body(JobFailed $event, string $jobClass, int $minutes): string
    {
        $exception = $event->exception;
        $firstLine = Str::of($exception->getMessage())->before("\n")->limit(self::MESSAGE_LIMIT)->toString();

        return implode("\n", array_filter([
            'A queued job has failed and will not be retried.',
            '',
            'Job: '.$jobClass,
            'Queue: '.$event->connectionName.' / '.$event->job->getQueue(),
            'Job UUID: '.($event->job->uuid() ?? 'n/a'),
            'Exception: '.$exception::class,
            'Message: '.$firstLine,
            ($requestId = Context::get(AssignRequestId::CONTEXT_KEY)) ? 'Request ID: '.$requestId : null,
            'Environment: '.app()->environment(),
            '',
            "Further failures of this job class are not mailed for {$minutes} minutes.",
            'Inspect and retry: php artisan queue:failed / queue:retry <uuid> (see infra/deploy.md "Failed jobs").',
        ], fn (?string $line): bool => $line !== null));
    }
}
