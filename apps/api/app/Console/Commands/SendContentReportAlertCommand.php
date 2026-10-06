<?php

namespace App\Console\Commands;

use App\Enums\ReportReason;
use App\Filament\Resources\ContentReports\ContentReportResource;
use App\Mail\NewContentReportsMail;
use App\Models\ContentReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Tells staff that reports are waiting (docs/notice-and-action.md). Scheduled
 * every 10 minutes: each run sends one summary of the open reports nobody has
 * been alerted about yet and marks them, so a burst of reports is one email
 * and a quiet queue sends nothing.
 */
class SendContentReportAlertCommand extends Command
{
    protected $signature = 'reports:alert-staff';

    protected $description = 'Email staff a summary of content reports that arrived since the last alert';

    public function handle(): int
    {
        $reports = ContentReport::query()
            ->open()
            ->whereNull('staff_alerted_at')
            ->get(['id', 'reason']);

        if ($reports->isEmpty()) {
            $this->info('No new reports.');

            return self::SUCCESS;
        }

        $reasons = $reports
            ->countBy(fn (ContentReport $report): string => $report->reason->value)
            ->map(fn (int $count, string $reason): array => [
                'label' => ReportReason::from($reason)->macedonianLabel(),
                'count' => $count,
            ])
            ->values()
            ->all();

        $mail = fn (): NewContentReportsMail => new NewContentReportsMail(
            newReports: $reports->count(),
            openReports: ContentReport::query()->open()->count(),
            reasons: $reasons,
            queueUrl: URL::to(ContentReportResource::getUrl('index')),
        );

        $recipients = $this->recipients();

        foreach ($recipients as $address) {
            Mail::to($address)->queue($mail());
        }

        // By id: a report that arrives while this runs waits for the next run.
        ContentReport::query()
            ->whereKey($reports->modelKeys())
            ->update(['staff_alerted_at' => now()]);

        $this->info("Alerted {$recipients->count()} recipient(s) about {$reports->count()} new report(s).");

        return self::SUCCESS;
    }

    /**
     * Everyone who can open the queue, plus the optional shared inbox.
     *
     * @return Collection<int, string>
     */
    private function recipients(): Collection
    {
        $staff = User::query()
            ->whereNull('anonymised_at')
            ->whereNull('suspended_at')
            // Members hold no queue rights; skip loading them.
            ->where(fn ($query) => $query->whereHas('roles')->orWhereHas('permissions'))
            ->get()
            ->filter(fn (User $user): bool => $user->can('content_reports.view'))
            ->map(fn (User $user): string => (string) $user->email);

        $shared = config('zdravje.reports.alert_email');

        if (is_string($shared) && trim($shared) !== '') {
            $staff->push(trim($shared));
        }

        return $staff
            ->filter(fn (string $email): bool => $email !== '')
            ->unique(fn (string $email): string => mb_strtolower($email))
            ->values();
    }
}
