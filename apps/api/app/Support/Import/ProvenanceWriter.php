<?php

namespace App\Support\Import;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes imported values onto directory records, one field at a time, with
 * provenance. The rules, in order:
 *
 * 1. A field staff LOCKED is never written (counted as "locked").
 * 2. Equal values only refresh provenance.
 * 3. The import may write when the record is new, the field is empty, or the
 *    current value is exactly what THIS source wrote last time (nobody else
 *    touched it since) or what the name cleanup made of it.
 * 4. Otherwise someone else set the value (staff, the doctor's own edit,
 *    another source): nothing is overwritten and a "conflict" review item
 *    shows both values.
 *
 * A write to a PUBLISHED record is also reported as "changed", one review
 * item per record per run listing the fields, so staff see what moved on
 * live profiles — except when only the casing of the source's own value
 * changed (a fixed casing rule; still in the run's diff) and no name or
 * quoted name loses the capital it starts with.
 */
final class ProvenanceWriter
{
    /** Provenance source of a value import:clean-names rewrote. */
    public const CLEANUP_SOURCE = 'cleanup';

    /** @var array<string, array<string, FieldProvenance>> provenance rows by "type:id", then field */
    private array $cache = [];

    /** @var array<string, array{subject: Model, label: string, fields: array<string, array{old: string|null, new: string|null}>}> */
    private array $changed = [];

    /** @var array<int, array<string, array{0: string|null, 1: int|null, 2: string|null}>> provenance of records not saved yet, by object id */
    private array $pending = [];

    /** Page the next values were read from (web sources); stored per field. */
    private ?string $sourceUrl = null;

    public function __construct(private readonly ImportContext $context) {}

    /**
     * Sets the page the following writes were read from (null for register files).
     */
    public function from(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl !== null ? mb_substr($sourceUrl, 0, 2000) : null;

        return $this;
    }

    /**
     * Preloads provenance for many records at once (one query per batch).
     *
     * @param  list<int>  $ids
     */
    public function preload(string $subjectType, array $ids): void
    {
        $missing = array_values(array_filter($ids, fn (int $id): bool => ! isset($this->cache[$subjectType.':'.$id])));

        foreach (array_chunk($missing, 500) as $chunk) {
            foreach ($chunk as $id) {
                $this->cache[$subjectType.':'.$id] = [];
            }

            FieldProvenance::query()
                ->where('subject_type', $subjectType)
                ->whereIn('subject_id', $chunk)
                ->get()
                ->each(function (FieldProvenance $row): void {
                    $this->cache[$row->subject_type.':'.$row->subject_id][$row->field] = $row;
                });
        }
    }

    /**
     * Applies one scalar attribute. The caller saves the model afterwards.
     *
     * @return 'written'|'unchanged'|'locked'|'conflict'
     */
    public function scalar(Model $subject, string $field, ?string $incoming, bool $isNew, ?int $sourceRecordId = null, string $label = ''): string
    {
        $incoming = $incoming === null || trim($incoming) === '' ? null : $incoming;
        $current = self::normalise($subject->getAttribute($field));
        $provenance = $isNew ? null : $this->provenance($subject, $field);

        if ($provenance?->locked) {
            if ($current !== $incoming) {
                $this->context->increment('fields_locked');
                $this->context->record(self::entity($subject), 'locked', $subject, $label, $field, $current, $incoming, 'Locked by staff; not changed.');
            }

            return 'locked';
        }

        if ($current === $incoming) {
            $this->remember($subject, $field, $incoming, $sourceRecordId, $provenance);

            return 'unchanged';
        }

        // A value the name cleanup rewrote (import:clean-names) still belongs
        // to the source that wrote it before: the cleanup only touches
        // fields no person edited.
        $ownValue = $provenance !== null
            && $this->originalSource($provenance) === $this->context->source
            && $provenance->value === $current;

        if (! $isNew && $current !== null && ! $ownValue) {
            // An empty incoming value never clears what someone else entered.
            if ($incoming === null) {
                return 'unchanged';
            }

            $this->conflict($subject, $field, $current, $incoming, $label);

            return 'conflict';
        }

        $subject->setAttribute($field, $incoming);
        $this->remember($subject, $field, $incoming, $sourceRecordId, $provenance);

        if (! $isNew) {
            $this->context->increment('fields_updated');
            $this->context->record(self::entity($subject), 'update', $subject, $label, $field, $current, $incoming);

            // Only the casing of the import's own value moved (a casing rule
            // fixed between runs, or a value import:clean-names re-cased):
            // written, but not listed as a „changed“ profile for staff —
            // unless it lower-cases the word a name or a quoted name starts
            // with („До Дент“ → „до Дент“): that can read wrong, so staff see it.
            $casingOnly = $ownValue && $current !== null && mb_strtolower($current, 'UTF-8') === mb_strtolower($incoming ?? '', 'UTF-8');

            if (! $casingOnly || self::lowersANameStart($current, (string) $incoming)) {
                $this->noteChange($subject, $label, $field, $current, $incoming);
            }
        }

        return 'written';
    }

    /**
     * Whether a casing-only change lower-cases the first letter of a word
     * that starts the value, a quoted name („…, "…, «…) or a bracket.
     */
    public static function lowersANameStart(string $current, string $incoming): bool
    {
        $before = preg_split('/\s+/u', trim($current)) ?: [];
        $after = preg_split('/\s+/u', trim($incoming)) ?: [];

        if (count($before) !== count($after)) {
            return false;
        }

        $start = true;

        foreach ($before as $index => $word) {
            $opens = preg_match('/^[„“"\'«(]/u', $word) === 1;
            $isStart = $start || $opens;
            // A quote standing alone opens the next word.
            $start = $opens && preg_match('/\p{L}/u', $word) !== 1;

            if (! $isStart) {
                continue;
            }

            preg_match('/\p{L}/u', $word, $old);
            preg_match('/\p{L}/u', $after[$index], $new);

            if (isset($old[0], $new[0]) && preg_match('/\p{Lu}/u', $old[0]) === 1 && preg_match('/\p{Ll}/u', $new[0]) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether staff locked a field (relations use this before syncing).
     */
    public function isLocked(Model $subject, string $field): bool
    {
        if (! $subject->exists) {
            return false;
        }

        return (bool) $this->provenance($subject, $field)?->locked;
    }

    /**
     * The source that last wrote a field (null: nobody recorded, e.g. staff).
     */
    public function sourceOf(Model $subject, string $field): ?string
    {
        $row = $subject->exists ? $this->provenance($subject, $field) : null;

        return $row !== null ? $this->originalSource($row) : null;
    }

    /** @var array<int, string|null> source of a source record, by id */
    private array $recordSources = [];

    /**
     * The source behind a value: for one the name cleanup rewrote, the
     * source of the record the value came from (kept on the row).
     */
    private function originalSource(FieldProvenance $row): ?string
    {
        if ($row->source !== self::CLEANUP_SOURCE) {
            return $row->source;
        }

        $recordId = $row->source_record_id;

        if ($recordId === null) {
            return null;
        }

        if (! array_key_exists($recordId, $this->recordSources)) {
            $source = DB::table('source_records')->where('id', $recordId)->value('source');
            $this->recordSources[$recordId] = is_string($source) ? $source : null;
        }

        return $this->recordSources[$recordId];
    }

    /**
     * Records provenance for a relation the importer manages (sorted ids).
     *
     * @param  list<int|string>  $ids
     */
    /**
     * Ids this source wrote to a relation last time.
     *
     * @return list<int>
     */
    public function lastWritten(Model $subject, string $field): array
    {
        $row = $subject->exists ? $this->provenance($subject, $field) : null;

        if ($row === null || $row->source !== $this->context->source || $row->value === null || $row->value === '') {
            return [];
        }

        return array_map('intval', explode(',', $row->value));
    }

    public function relation(Model $subject, string $field, array $ids, ?int $sourceRecordId = null): void
    {
        sort($ids);
        $this->remember($subject, $field, implode(',', $ids), $sourceRecordId, $this->provenance($subject, $field));
    }

    public function noteChange(Model $subject, string $label, string $field, ?string $old, ?string $new): void
    {
        if (! (bool) $subject->getAttribute('is_published')) {
            return;
        }

        $key = self::entity($subject).':'.$subject->getKey();
        $this->changed[$key] ??= ['subject' => $subject, 'label' => $label, 'fields' => []];
        $this->changed[$key]['fields'][$field] = ['old' => $old, 'new' => $new];
    }

    /**
     * One "changed" review item per published record touched in this run.
     */
    public function flushChanges(): void
    {
        foreach ($this->changed as $key => $change) {
            $this->context->review(
                ImportReviewKind::Changed,
                $key.':run'.$this->context->run->getKey(),
                $change['label'],
                ['fields' => $change['fields']],
                $change['subject'],
            );
        }

        $this->changed = [];
    }

    private function conflict(Model $subject, string $field, ?string $current, string $incoming, string $label): void
    {
        $this->context->increment('fields_conflict');
        $this->context->record(self::entity($subject), 'conflict', $subject, $label, $field, $current, $incoming, 'Set by someone else; not overwritten.');
        $this->context->review(
            ImportReviewKind::Conflict,
            self::entity($subject).':'.$subject->getKey().':'.$field,
            $label,
            ['field' => $field, 'current' => $current, 'incoming' => $incoming],
            $subject,
        );
    }

    private function provenance(Model $subject, string $field): ?FieldProvenance
    {
        $key = self::entity($subject).':'.$subject->getKey();

        if (! array_key_exists($key, $this->cache)) {
            $this->preload(self::entity($subject), [(int) $subject->getKey()]);
        }

        return $this->cache[$key][$field] ?? null;
    }

    private function remember(Model $subject, string $field, ?string $value, ?int $sourceRecordId, ?FieldProvenance $existing): void
    {
        if (! $subject->exists) {
            // Written after the first save; see rememberAfterCreate().
            $this->pending[spl_object_id($subject)][$field] = [$value, $sourceRecordId, $this->sourceUrl];

            return;
        }

        if ($existing !== null
            && $existing->source === $this->context->source
            && $existing->value === $value
            && $existing->source_record_id === $sourceRecordId
            && $existing->source_url === $this->sourceUrl) {
            return;
        }

        $row = $existing ?? new FieldProvenance([
            'subject_type' => self::entity($subject),
            'subject_id' => $subject->getKey(),
            'field' => $field,
        ]);

        $row->fill([
            'source' => $this->context->source,
            'source_record_id' => $sourceRecordId,
            'source_url' => $this->sourceUrl,
            'value' => $value,
            'observed_at' => $this->context->observedAt,
        ])->save();

        $this->cache[self::entity($subject).':'.$subject->getKey()][$field] = $row;
    }

    /**
     * Provenance for a record created in this run: called once it has an id.
     */
    public function rememberAfterCreate(Model $subject): void
    {
        $fields = $this->pending[spl_object_id($subject)] ?? [];
        unset($this->pending[spl_object_id($subject)]);

        if ($fields === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($fields as $field => [$value, $sourceRecordId, $sourceUrl]) {
            $rows[] = [
                'subject_type' => self::entity($subject),
                'subject_id' => $subject->getKey(),
                'field' => $field,
                'source' => $this->context->source,
                'source_record_id' => $sourceRecordId,
                'source_url' => $sourceUrl,
                'value' => $value,
                'observed_at' => $this->context->observedAt,
                'locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('field_provenance')->insert($rows);
        unset($this->cache[self::entity($subject).':'.$subject->getKey()]);
    }

    /**
     * Staff lock or unlock a field; locking keeps the current provenance.
     */
    public static function setLock(Model $subject, string $field, bool $locked, ?User $by): void
    {
        $row = FieldProvenance::query()->firstOrNew([
            'subject_type' => self::entity($subject),
            'subject_id' => $subject->getKey(),
            'field' => $field,
        ]);

        $row->forceFill([
            'locked' => $locked,
            'locked_by_id' => $locked ? $by?->getKey() : null,
            'locked_at' => $locked ? now() : null,
        ])->save();
    }

    public static function entity(Model $subject): string
    {
        return ImportReviewItem::subjectTypeOf($subject);
    }

    public static function normalise(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
