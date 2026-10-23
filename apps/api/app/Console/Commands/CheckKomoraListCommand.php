<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\DataOps\ImportAlerter;
use App\Support\DataOps\ImportSchedule;
use App\Support\Licences\KomoraLicenceFetcher;
use App\Support\Licences\KomoraListDate;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Is there a Лекарска комора list newer than the last one we imported?
 * Monthly (routes/console.php); it never imports, it tells staff — once
 * per new list — in the panel's bell (everyone with licences.manage) and
 * the import alert inbox (ImportAlerter).
 *
 * Polite like the import (KomoraLicenceFetcher::probe()): robots.txt, our
 * User-Agent with the contact, only the list page — plus a conditional HEAD
 * per file when the page states no list date. Each check is an import_runs
 * row (source „komora-check“): what the page showed, and what was announced.
 */
class CheckKomoraListCommand extends Command
{
    public const SOURCE = 'komora-check';

    protected $signature = 'import:komora-check';

    protected $description = 'Check the Лекарска комора page for a licence list newer than the last import, and tell staff once';

    public function handle(KomoraLicenceFetcher $fetcher, ImportAlerter $alerter): int
    {
        $run = ImportRun::start(self::SOURCE, false);
        $applied = KomoraListDate::latestApplied();

        try {
            $probe = $fetcher->probe();
        } catch (Throwable $exception) {
            report($exception);
            $run->fail($exception);
            $this->error('The Комора list page could not be checked: '.$exception->getMessage());

            return self::FAILURE;
        }

        $pageDate = $probe['list_date'];
        [$newer, $fingerprint] = $this->compare($pageDate, $applied, $probe['urls'], $probe['changed']);
        $meta = [
            'page_list_date' => $pageDate?->toDateString(),
            'applied_list_date' => $applied?->toDateString(),
            'newer' => $newer,
            'fingerprint' => $fingerprint,
        ];

        if (! $newer) {
            $run->forceFill(['source_meta' => $meta]);
            $run->finish([], ImportRunStatus::NotModified);
            $this->info('No newer licence list'.($pageDate !== null ? ' (the page shows the list of '.$pageDate->format('d.m.Y').')' : '').'.');

            return self::SUCCESS;
        }

        if ($this->alreadyAnnounced($fingerprint)) {
            $run->forceFill(['source_meta' => $meta + ['notified' => false, 'already_notified' => true]]);
            $run->finish([]);
            $this->info('A newer licence list is waiting; staff were already told about it.');

            return self::SUCCESS;
        }

        $message = $this->message($pageDate, $applied);
        $staff = $this->notifyStaff($message);
        $mailed = $alerter->send(self::SOURCE, ImportAlerter::KIND_NEW_LIST, note: $message, reviewUrl: URL::to(ImportRunResource::getUrl('index')));
        $notified = $staff > 0 || $mailed;

        $run->forceFill(['source_meta' => $meta + [
            'notified' => $notified,
            'notified_fingerprint' => $notified ? $fingerprint : null,
            'notified_staff' => $staff,
            'mailed' => $mailed,
        ]]);
        $run->finish(['notified_staff' => $staff, 'mailed' => (int) $mailed]);

        $this->info($message);

        if (! $notified) {
            $this->warn('Nobody could be told (no staff with licences.manage, no import alert inbox); the next check tries again.');
        }

        return self::SUCCESS;
    }

    /**
     * The page's list date decides; without one, a file we never downloaded
     * or one that changed since (conditional HEAD).
     *
     * @param  list<string>  $urls
     * @param  list<string>  $changed
     * @return array{0: bool, 1: string}
     */
    private function compare(?CarbonImmutable $pageDate, ?CarbonImmutable $applied, array $urls, array $changed): array
    {
        if ($pageDate !== null) {
            return [$applied === null || $pageDate->gt($applied), 'date:'.$pageDate->toDateString()];
        }

        sort($urls);
        sort($changed);

        return [$changed !== [], 'files:'.sha1(implode("\n", [...$urls, '--', ...$changed]))];
    }

    private function alreadyAnnounced(string $fingerprint): bool
    {
        return ImportRun::query()
            ->where('source', self::SOURCE)
            ->where('status', ImportRunStatus::Succeeded)
            ->latest('id')
            ->limit(50)
            ->get()
            ->contains(fn (ImportRun $run): bool => ($run->source_meta['notified_fingerprint'] ?? null) === $fingerprint);
    }

    private function message(?CarbonImmutable $pageDate, ?CarbonImmutable $applied): string
    {
        $what = $pageDate !== null
            ? 'The Лекарска комора page shows a licence list of '.$pageDate->format('d.m.Y')
            : 'The Лекарска комора list files changed (the page states no date)';
        $last = $applied !== null ? '; the last one imported is of '.$applied->format('d.m.Y') : '; no list has been imported yet';
        $next = ImportSchedule::shouldRun('komora')
            ? 'The scheduled import (import:komora-licences, monthly on the 3rd) will apply it.'
            : 'Import it with php artisan import:komora-licences'.($pageDate === null ? ' --list-date=dd.mm.yyyy' : '').' (doctors kept verified on a licence grace are decided by the new list).';

        return $what.$last.'. '.$next;
    }

    /** The panel's bell for everyone who may manage the licence list; returns how many. */
    private function notifyStaff(string $message): int
    {
        $staff = $this->staff();
        $notification = Notification::make()
            ->title('New Лекарска комора licence list')
            ->body($message)
            ->info()
            ->toDatabase();

        foreach ($staff as $user) {
            // Written now: Filament's database notification is queued, and
            // the queue may be the very thing not running.
            $user->notifyNow($notification);
        }

        return $staff->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        return User::query()
            ->whereNull('anonymised_at')
            ->whereNull('suspended_at')
            ->where(fn ($query) => $query->whereHas('roles')->orWhereHas('permissions'))
            ->get()
            ->filter(fn (User $user): bool => $user->can('licences.manage'))
            ->values();
    }
}
