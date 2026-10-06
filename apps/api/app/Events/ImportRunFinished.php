<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by a source import (`import:fzom`, `import:komora-licences`)
 * when a run ends, successfully or not. App\Listeners\AlertOnImportRun mails
 * the import alert inbox about a failed run or one that changed an unusual
 * share of the records it saw (config/data_ops.php).
 *
 * Counts only. Never put a name, a licence or facsimile number, or a raw
 * source record in here: the alert mail is built from it.
 *
 * Contract for the import code (docs/data-import.md, "Alerts"):
 * `$source` is `fzom` or `komora`; `$seen` is the number of source records
 * the run read after filtering; `$created`, `$updated` and `$missing` count
 * profiles (or licence links) the run created, changed, or found missing;
 * `$conflicts` and `$unmatched` count entries sent to the review queue.
 */
final class ImportRunFinished
{
    use Dispatchable;

    public function __construct(
        public readonly string $source,
        public readonly bool $succeeded,
        public readonly int $seen = 0,
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $missing = 0,
        public readonly int $conflicts = 0,
        public readonly int $unmatched = 0,
        public readonly int|string|null $runId = null,
        public readonly ?string $error = null,
        public readonly ?string $reviewUrl = null,
    ) {}

    /** Profiles the run would add, change or flag as gone. */
    public function changed(): int
    {
        return $this->created + $this->updated + $this->missing;
    }

    public function isLargeDiff(float $ratio, int $minimum): bool
    {
        $changed = $this->changed();

        if ($changed < max(1, $minimum)) {
            return false;
        }

        // Nothing seen but something changed (e.g. every record went
        // missing because the file came back empty) is as large as it gets.
        return $this->seen === 0 || $changed / $this->seen >= $ratio;
    }
}
