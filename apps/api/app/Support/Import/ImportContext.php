<?php

namespace App\Support\Import;

use App\Enums\ImportReviewKind;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * State shared by everything one import run touches: the run row, whether it
 * is a dry run, the counters, and the diff summary written for staff.
 *
 * Review items raised during a dry run are counted but rolled back with the
 * rest of the run (ImportRunner wraps a dry run in one transaction), so the
 * counts say what an apply would queue.
 */
final class ImportContext
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<array{entity: string, action: string, record: string, label: string, field: string, old: string, new: string, note: string}> */
    private array $diff = [];

    public readonly CarbonImmutable $observedAt;

    public function __construct(
        public readonly ImportRun $run,
        public readonly string $source,
        public readonly bool $dryRun,
        ?CarbonImmutable $observedAt = null,
    ) {
        $this->observedAt = $observedAt ?? CarbonImmutable::now();
    }

    public function increment(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        ksort($this->counts);

        return $this->counts;
    }

    public function count(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }

    /**
     * One line of the diff summary. Internal keys (ФЗО facsimile, licence
     * and tax numbers) never go in here: `record` is our own id.
     */
    public function record(string $entity, string $action, ?Model $subject, string $label, string $field = '', mixed $old = null, mixed $new = null, string $note = ''): void
    {
        $this->diff[] = [
            'entity' => $entity,
            'action' => $action,
            'record' => $subject?->getKey() !== null ? (string) $subject->getKey() : '',
            'label' => $label,
            'field' => $field,
            'old' => self::stringify($old),
            'new' => self::stringify($new),
            'note' => $note,
        ];
    }

    /**
     * @return list<array{entity: string, action: string, record: string, label: string, field: string, old: string, new: string, note: string}>
     */
    public function diff(): array
    {
        return $this->diff;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function review(ImportReviewKind $kind, string $itemKey, string $title, array $details = [], ?Model $subject = null): void
    {
        $item = ImportReviewItem::raise($this->source, $kind, $itemKey, $title, $details, $subject, $this->run->getKey());

        // Counts (and so the alerts) are what is new in this run: not an
        // item refreshed while open, nor one staff dismissed before.
        if ($item->wasRecentlyCreated) {
            $this->increment('review_'.$kind->value);
        }
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => implode('; ', array_map(fn ($item): string => (string) $item, $value)),
            default => mb_substr((string) $value, 0, 500),
        };
    }
}
