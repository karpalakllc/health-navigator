<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportRunner;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use App\Support\Import\Pharmacies\OnDutyScheduleFetcher;
use App\Support\Import\Pharmacies\OnDutyScheduleParser;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Console\Command;
use Throwable;

/**
 * ФЗОМ's monthly on-duty pharmacy schedule → pharmacy_duty_shifts
 * (docs/urgent-care.md § On-duty pharmacies).
 *
 * Without --month: the current month (Skopje) and, when the page already
 * lists it, the next one. A month the page does not list yet is not an
 * error (ФЗОМ sometimes publishes a week into the month): the run ends as
 * „not modified“ with `not_published`, and the next scheduled run retries.
 */
class ImportOnDutyPharmaciesCommand extends Command
{
    protected $signature = 'import:on-duty-pharmacies
                            {--month= : YYYY-MM; default: this month and the next one if published}
                            {--file= : Import a local .xlsx instead of downloading (needs --month unless the sheet names it)}
                            {--dry-run : Do everything except keeping the changes}
                            {--force : Download and re-import although the file did not change}';

    protected $description = 'Import ФЗОМ\'s monthly on-duty pharmacy schedule';

    public function handle(ImportRunner $runner, OnDutyScheduleFetcher $fetcher, OnDutyScheduleParser $parser, OnDutyPharmacyImporter $importer): int
    {
        if (! (bool) config('import.on_duty_pharmacies.enabled')) {
            $this->warn('The on-duty pharmacy import is switched off (IMPORT_ON_DUTY_PHARMACIES).');

            return self::SUCCESS;
        }

        $option = $this->option('month');
        $month = is_string($option) && $option !== '' ? $option : null;

        if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $this->error('--month must be YYYY-MM.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            if (! is_file($file)) {
                $this->error("No such file: {$file}");

                return self::INVALID;
            }

            return $this->report($this->runFile($runner, $parser, $importer, $file, $month, $dryRun));
        }

        // A page that cannot be read fails the run for this month (recorded
        // like any failed import), not the command before any run exists.
        try {
            $listed = $fetcher->listed();
        } catch (Throwable $exception) {
            $listed = $exception;
        }

        $now = CarbonImmutable::now('Europe/Skopje');
        // [month, optional]: the next month is skipped silently while unpublished.
        $months = $month !== null
            ? [[$month, false]]
            : [[$now->format('Y-m'), false], [$now->addMonthNoOverflow()->format('Y-m'), true]];
        $status = self::SUCCESS;

        foreach ($months as [$target, $optional]) {
            $run = $this->runMonth($runner, $fetcher, $parser, $importer, $listed, $target, $dryRun, $force, $optional);

            if ($run === null) {
                continue;
            }

            $status = max($status, $this->report($run));
        }

        return $status;
    }

    private function runFile(ImportRunner $runner, OnDutyScheduleParser $parser, OnDutyPharmacyImporter $importer, string $file, ?string $month, bool $dryRun): ?ImportRun
    {
        return $this->guarded(fn () => $runner->run(OnDutyPharmacyImporter::SOURCE, $dryRun, null, function (ImportContext $context) use ($parser, $importer, $file, $month): array {
            $parsed = $parser->parse($file, $month);
            $importer->import($context, $parsed, null);

            return ['month' => $parsed['month'], 'file' => basename($file), 'sha256' => hash_file('sha256', $file)];
        }));
    }

    /**
    /**
     * @param  list<array{month: string, url: string, label: string}>|Throwable  $listed
     */
    private function runMonth(ImportRunner $runner, OnDutyScheduleFetcher $fetcher, OnDutyScheduleParser $parser, OnDutyPharmacyImporter $importer, array|Throwable $listed, string $month, bool $dryRun, bool $force, bool $optional): ?ImportRun
    {
        $link = is_array($listed) ? collect($listed)->firstWhere('month', $month) : null;

        if ($link === null && $optional) {
            return null;
        }

        return $this->guarded(fn () => $runner->run(OnDutyPharmacyImporter::SOURCE, $dryRun, null, function (ImportContext $context) use ($fetcher, $parser, $importer, $listed, $month, $link, $force): array {
            if ($listed instanceof Throwable) {
                throw $listed;
            }

            if ($link === null) {
                $context->increment('not_published');

                return ['not_modified' => true, 'month' => $month, 'reason' => 'not_published'];
            }

            $download = $fetcher->download($link, $this->previous($month), $force);
            $meta = [
                'month' => $month,
                'url' => $link['url'],
                'label' => $link['label'],
                'path' => $download['path'],
                'sha256' => $download['sha256'],
                'etag' => $download['etag'],
                'last_modified' => $download['last_modified'],
                'bytes' => $download['bytes'],
            ];

            if ($download['not_modified'] && ! $force) {
                return $meta + ['not_modified' => true];
            }

            $importer->import($context, $parser->parse($download['absolute_path'], $month), $link['url']);

            return $meta;
        }));
    }

    /**
     * What the last applied run of this month stored (for a conditional GET).
     *
     * @return array<string, mixed>|null
     */
    private function previous(string $month): ?array
    {
        return ImportRun::query()
            ->where('source', OnDutyPharmacyImporter::SOURCE)
            ->where('dry_run', false)
            ->whereIn('status', [ImportRunStatus::Succeeded, ImportRunStatus::NotModified])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ImportRun $run): array => (array) $run->source_meta)
            ->first(fn (array $meta): bool => ($meta['month'] ?? null) === $month && isset($meta['path']));
    }

    private function guarded(Closure $run): ?ImportRun
    {
        try {
            return $run();
        } catch (ImportAlreadyRunning) {
            $this->warn('An on-duty pharmacy import is already running.');

            return null;
        }
    }

    private function report(?ImportRun $run): int
    {
        if ($run === null) {
            return self::SUCCESS;
        }

        $meta = (array) $run->source_meta;
        $this->info(sprintf('%s %s: %s', $run->dry_run ? 'Dry run' : 'Run', $meta['month'] ?? '?', $run->status->value));

        foreach ((array) $run->counts as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        if ($run->status === ImportRunStatus::Failed) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
