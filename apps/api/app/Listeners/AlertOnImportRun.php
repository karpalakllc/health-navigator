<?php

namespace App\Listeners;

use App\Events\ImportRunFinished;
use App\Support\DataOps\ImportAlerter;

/**
 * Mails the import alert inbox when a source import fails or changes an
 * unusual share of what it saw (config/data_ops.php). A large diff is often
 * a changed source format or a truncated download rather than real news, so
 * staff should look at the run in the review queue before publishing.
 *
 * Registered by listener discovery (app/Listeners); do not also
 * Event::listen() it.
 */
class AlertOnImportRun
{
    public function __construct(private readonly ImportAlerter $alerter) {}

    public function handle(ImportRunFinished $event): void
    {
        $counts = [
            'seen' => $event->seen,
            'created' => $event->created,
            'updated' => $event->updated,
            'missing' => $event->missing,
            'conflicts' => $event->conflicts,
            'unmatched' => $event->unmatched,
        ];

        if (! $event->succeeded) {
            $this->alerter->send($event->source, ImportAlerter::KIND_FAILED, $counts, $event->error ?? 'Увозот не заврши.', $event->reviewUrl);

            return;
        }

        $ratio = (float) config('data_ops.alerts.large_diff_ratio', 0.10);
        $minimum = (int) config('data_ops.alerts.large_diff_min', 25);

        if ($event->isLargeDiff($ratio, $minimum)) {
            $this->alerter->send($event->source, ImportAlerter::KIND_LARGE_DIFF, $counts, reviewUrl: $event->reviewUrl);
        }
    }
}
