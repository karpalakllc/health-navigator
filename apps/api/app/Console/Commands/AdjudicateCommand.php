<?php

namespace App\Console\Commands;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Verification\Engine\Reason;
use App\Support\Verification\Engine\VerificationEngine;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use Illuminate\Console\Command;

/**
 * The verification engine by hand (docs/verification.md). It also runs after
 * every import apply and nightly. Prints counts only — never names.
 */
class AdjudicateCommand extends Command
{
    protected $signature = 'import:adjudicate
        {--dry-run : Decide and count, write nothing}
        {--report : Print the summary for the owner (what is verified and why not, and the open uncertain cases); writes nothing}';

    protected $description = 'Verify doctor, facility and pharmacy profiles from the import evidence, and report the uncertain cases';

    public function handle(VerificationEngine $engine, VerifiedDraftPublisher $publisher): int
    {
        $report = (bool) $this->option('report');

        try {
            $run = $engine->run($report || (bool) $this->option('dry-run'));
        } catch (ImportAlreadyRunning $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $this->line(sprintf('Run #%d (%s): %s', $run->getKey(), $run->dry_run ? 'dry run' : 'apply', $run->status->value));

        if ($run->status === ImportRunStatus::Failed) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        $report ? $this->report($run, $publisher) : $this->counts($run);

        return self::SUCCESS;
    }

    private function counts(ImportRun $run): void
    {
        $this->table(['count', 'value'], collect($run->counts ?? [])->map(fn ($value, $key) => [$key, $value])->values()->all());
    }

    private function report(ImportRun $run, VerifiedDraftPublisher $publisher): void
    {
        $counts = collect($run->counts ?? []);
        $section = fn (string $infix) => $counts
            ->filter(fn ($value, string $key): bool => str_contains($key, $infix))
            ->map(fn ($value, string $key): array => [self::subject($key), substr($key, strpos($key, '.') + 1), $value, Reason::DESCRIPTIONS[substr($key, strpos($key, '.') + 1)] ?? ''])
            ->values()->all();

        $this->newLine();
        $this->info('Verified, by rule');
        $this->table(['profiles', 'rule', 'count', ''], $section('_verified.'));

        $this->info('Not verified, by reason');
        $this->table(['profiles', 'reason', 'count', 'meaning'], $section('_unverified.'));

        $kept = $counts->filter(fn ($value, string $key): bool => str_ends_with($key, '_result.staff_decision_kept'))->sum();
        $this->line(sprintf('Staff decisions kept as they are: %d', $kept));

        $this->newLine();
        $this->info('Open review items, by reason (what is left for a person)');
        $open = ImportReviewItem::query()->open()
            ->whereIn('kind', [ImportReviewKind::Uncertain, ImportReviewKind::Unmatched, ImportReviewKind::Conflict, ImportReviewKind::Missing])
            ->get(['source', 'kind', 'item_key', 'details'])
            // Unmapped wordings carry no reason: their key says what they are.
            ->groupBy(fn (ImportReviewItem $item): string => $item->kind->value.'|'.$item->source.'|'.($item->details['reason'] ?? (str_starts_with($item->item_key, 'specialty:') ? 'unmapped_specialty_wording' : '—')))
            ->map(fn ($items, string $key): array => [...explode('|', $key), $items->count()])
            ->sortByDesc(fn (array $row): int => $row[3])
            ->values()->all();
        $this->table(['kind', 'source', 'reason', 'open'], $open);
        $this->line(sprintf('Total open: %d', array_sum(array_column($open, 3))));

        $this->newLine();
        $drafts = ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->count();
        $this->line(sprintf('Imported drafts waiting („new“): %d, of which verified and ready for „Објави ги сите верифицирани“: %d', $drafts, $publisher->count()));
        $this->line(sprintf('… unverified, in ФЗОМ without a licence, ready for „Објави ги и неверифицираните од ФЗОМ“: %d', $publisher->countFzomUnverified()));
    }

    private static function subject(string $key): string
    {
        return strstr($key, '_', true) ?: $key;
    }
}
