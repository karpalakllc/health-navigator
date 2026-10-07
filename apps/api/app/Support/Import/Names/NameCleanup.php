<?php

namespace App\Support\Import\Names;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * import:clean-names — applies the name rules (PersonName, DoctorTitle,
 * FacilityName) to the profiles already in the directory, the way every
 * import now writes new ones.
 *
 * - Only values an import wrote and nobody changed since: a field that is
 *   locked, or whose value differs from its provenance (staff, the doctor),
 *   is reported as skipped and never touched.
 * - A rewritten value keeps its provenance row, now with the source
 *   „cleanup“ (ProvenanceWriter treats it as the original source's value, so
 *   the next import neither fights it nor raises a conflict).
 * - Each change is one activity-log entry (log `name_cleanup`, old → new).
 * - What a rule sees but must not decide becomes an Uncertain review item
 *   (source `cleanup`, reason `name_cleanup`) with the proposed value;
 *   possible duplicates are grouped (DuplicateFinder).
 * - A private CSV (the import disk, never public) lists every change, skip
 *   and uncertain case; it is the run's diff in Import runs.
 *
 * A dry run decides the same and writes only the run row and the CSV.
 */
final class NameCleanup
{
    public const SOURCE = ProvenanceWriter::CLEANUP_SOURCE;

    public const REASON = 'name_cleanup';

    public const LOG = 'name_cleanup';

    /** Strong evidence for a surname-first name (DoctorNameOrder). */
    private const ORDER_GIVEN_MIN = 10;

    private const ORDER_SURNAME_MIN = 3;

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}> */
    private array $rows = [];

    /** @var array<string, array<int, array<string, FieldProvenance>>> */
    private array $provenance = [];

    /** @var array<string, true> sorted name keys on the Комора list */
    private array $licenceKeys = [];

    /** @var array<string, int> */
    private array $asFirst = [];

    /** @var array<string, int> */
    private array $asLast = [];

    private bool $apply = false;

    /** @var array<int, true> website drafts a duplicate group proposes to merge away */
    private array $merging = [];

    private ?User $by = null;

    private ?ImportRun $run = null;

    public function __construct(private readonly DuplicateFinder $duplicates) {}

    public function run(bool $apply, ?User $by = null): ImportRun
    {
        $lock = ImportAlreadyRunning::lock(self::SOURCE);

        try {
            $this->apply = $apply;
            $this->by = $by;
            $this->counts = [];
            $this->rows = [];
            $this->run = ImportRun::start(self::SOURCE, ! $apply, $by);

            try {
                $this->loadContext();
                $groups = $this->duplicates->find();
                $this->merging = [];

                foreach ($groups as $group) {
                    foreach ($group['details']['pairs'] ?? [] as [$from]) {
                        $this->merging[(int) $from] = true;
                    }
                }

                $this->batched = [];
                $this->doctors();
                $this->facilities();
                $this->batchItems();
                $this->duplicateItems($groups);
            } catch (Throwable $exception) {
                $this->writeReport();
                $this->run->forceFill(['counts' => $this->sortedCounts()]);
                $this->run->fail($exception);
                report($exception);

                return $this->run;
            }

            $this->writeReport();
            $this->run->finish($this->sortedCounts());

            return $this->run;
        } finally {
            $lock->release();
        }
    }

    private function loadContext(): void
    {
        $this->provenance = [];

        FieldProvenance::query()
            ->whereIn('field', ['full_name', 'title', 'name'])
            ->get()
            ->each(function (FieldProvenance $row): void {
                $this->provenance[$row->subject_type][(int) $row->subject_id][$row->field] = $row;
            });

        $this->licenceKeys = [];

        DB::table('komora_licences')->select('full_name')->orderBy('id')->chunk(2000, function ($rows): void {
            foreach ($rows as $row) {
                $this->licenceKeys[NameKey::sorted((string) $row->full_name)] = true;
            }
        });

        // Which words are given names and which surnames, from the whole
        // directory (for names written surname first).
        $this->asFirst = [];
        $this->asLast = [];

        Doctor::query()->select(['id', 'full_name'])->chunkById(2000, function ($doctors): void {
            foreach ($doctors as $doctor) {
                $words = explode(' ', mb_strtoupper(PersonName::clean((string) $doctor->full_name)->value, 'UTF-8'));

                if (count($words) >= 2) {
                    $this->asFirst[$words[0]] = ($this->asFirst[$words[0]] ?? 0) + 1;
                    $last = $words[count($words) - 1];
                    $this->asLast[$last] = ($this->asLast[$last] ?? 0) + 1;
                }
            }
        });
    }

    private function doctors(): void
    {
        Doctor::query()->orderBy('id')->chunkById(500, function ($doctors): void {
            $this->transaction(function () use ($doctors): void {
                foreach ($doctors as $doctor) {
                    $this->count('doctors_checked');
                    $this->doctorName($doctor);
                    $this->doctorTitle($doctor);
                }
            });
        });
    }

    private function doctorName(Doctor $doctor): void
    {
        $current = (string) $doctor->full_name;
        $cleaned = PersonName::clean($current);
        $value = $cleaned->value;
        $changes = $cleaned->changes;
        $uncertain = $cleaned->uncertain;
        $suggestion = $cleaned->suggestion;

        // „Петрова Ана“ when the whole directory writes „Ана … Петрова“.
        $order = $uncertain === null ? $this->order($value) : null;

        if ($order !== null && $order['strong']) {
            $value = $order['name'];
            $changes[] = 'order';
        } elseif ($order !== null) {
            $uncertain = 'name_order';
            $suggestion = $order['name'];
        }

        // A new matching key could break a licence match the old name had.
        if ($value !== $current && NameKey::sorted($value) !== NameKey::sorted($current)
            && isset($this->licenceKeys[NameKey::sorted($current)]) && ! isset($this->licenceKeys[NameKey::sorted($value)])) {
            $uncertain ??= 'licence_name_differs';
            $suggestion ??= $value;
            $value = $current;
            $changes = [];
        }

        if ($value !== $current) {
            $this->change($doctor, 'full_name', $current, $value, $changes);
        }

        if ($cleaned->title !== null && $doctor->title === null) {
            $this->change($doctor, 'title', null, $cleaned->title, ['title_in_name'], fallbackRecordFrom: 'full_name');
        }

        // A Latin-script draft that a duplicate group would merge into its
        // Cyrillic ФЗОМ profile needs no rename.
        if ($uncertain === 'latin_script' && isset($this->merging[(int) $doctor->getKey()])) {
            $this->count('doctor.full_name.uncertain_in_merge_group');
            $uncertain = null;
        }

        if ($uncertain !== null) {
            $this->uncertain($doctor, 'full_name', $value, $suggestion, $uncertain);
        }
    }

    private function doctorTitle(Doctor $doctor): void
    {
        if ($doctor->title === null) {
            return;
        }

        $current = (string) $doctor->title;
        $cleaned = DoctorTitle::clean($current);

        if ($cleaned === null) {
            return;
        }

        $value = $cleaned->value !== '' ? $cleaned->value : null;

        if ($value !== $current && $cleaned->uncertain === null) {
            $this->change($doctor, 'title', $current, $value, $cleaned->changes ?: ['title_format']);
        }

        if ($cleaned->uncertain !== null) {
            $this->uncertain($doctor, 'title', $current, null, $cleaned->uncertain);
        }
    }

    private function facilities(): void
    {
        Facility::query()->orderBy('id')->chunkById(500, function ($facilities): void {
            $this->transaction(function () use ($facilities): void {
                foreach ($facilities as $facility) {
                    $this->count('facilities_checked');
                    $current = (string) $facility->name;
                    $cleaned = FacilityName::clean($current, $facility->city);

                    if ($cleaned->value !== $current && $cleaned->value !== '') {
                        $this->change($facility, 'name', $current, $cleaned->value, $cleaned->changes);
                    }

                    if ($cleaned->uncertain !== null) {
                        $this->uncertain($facility, 'name', $cleaned->value, $cleaned->suggestion, $cleaned->uncertain);
                    }
                }
            });
        });
    }

    /**
     * Groups of profiles that look like one person, as review items.
     */
    /**
     * @param  list<array{kind: string, key: string, title: string, details: array<string, mixed>, facility_id: int|null, priority: int, doctor_ids: list<int>, label: string}>  $groups
     */
    private function duplicateItems(array $groups): void
    {
        foreach ($groups as $group) {
            $this->count('duplicates.'.$group['kind']);
            $this->count('duplicates.'.$group['kind'].'_profiles', count($group['doctor_ids']));

            foreach ($group['doctor_ids'] as $id) {
                $this->row('doctor', (string) $id, 'full_name', 'duplicate:'.$group['kind'], '', '', 'uncertain', $group['label']);
            }

            if ($this->apply) {
                $subject = $group['facility_id'] !== null ? Facility::query()->find($group['facility_id']) : null;
                $item = ImportReviewItem::raise(self::SOURCE, ImportReviewKind::Uncertain, $group['key'], $group['title'], $group['details'], $subject, (int) $this->run?->getKey(), $group['priority']);

                if ($item->wasRecentlyCreated) {
                    $this->count('review_items_created');
                }
            }
        }
    }

    /**
     * @param  list<string>  $categories
     */
    private function change(Doctor|Facility $subject, string $field, ?string $old, ?string $new, array $categories, ?string $fallbackRecordFrom = null): void
    {
        $type = ImportReviewItem::subjectTypeOf($subject);
        $id = (int) $subject->getKey();
        $category = implode('+', $categories);
        $provenance = $this->provenance[$type][$id][$field] ?? null;
        $state = $this->state($provenance, $old);

        if ($state !== 'ok') {
            $this->count($type.'.'.$field.'.skipped_'.$state);
            $this->row($type, (string) $id, $field, $category, $old, $new, 'skipped_'.$state, $state === 'locked' ? 'Field locked by staff' : 'Set by staff or the doctor; not changed');

            return;
        }

        foreach ($categories as $one) {
            $this->count($type.'.'.$field.'.'.$one);
        }

        $this->count($type.'.'.$field.'.changed');
        $this->row($type, (string) $id, $field, $category, $old, $new, $this->apply ? 'applied' : 'would_apply', '');

        if (! $this->apply) {
            return;
        }

        $subject->setAttribute($field, $new);
        activity()->withoutLogging(fn () => $subject->save());

        $recordId = $provenance->source_record_id ?? ($fallbackRecordFrom !== null ? ($this->provenance[$type][$id][$fallbackRecordFrom] ?? null)?->source_record_id : null);
        $row = FieldProvenance::query()->updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $id, 'field' => $field],
            ['source' => self::SOURCE, 'value' => $new, 'observed_at' => now(), 'source_record_id' => $recordId],
        );
        $this->provenance[$type][$id][$field] = $row;

        self::log($subject, $field, $old, $new, $category, $this->by);
    }

    /**
     * 'ok' when an import wrote the current value and nobody changed it
     * since; 'locked'; or 'staff' (someone else set it).
     */
    private function state(?FieldProvenance $provenance, ?string $current): string
    {
        if ($provenance?->locked) {
            return 'locked';
        }

        if ($provenance === null) {
            return $current === null ? 'ok' : 'staff';
        }

        // A suggestion staff accepted from the review queue.
        if ($provenance->source === 'staff') {
            return 'staff';
        }

        return ProvenanceWriter::normalise($provenance->value) === ProvenanceWriter::normalise($current) ? 'ok' : 'staff';
    }

    private function uncertain(Doctor|Facility $subject, string $field, string $current, ?string $suggestion, string $problem): void
    {
        $type = ImportReviewItem::subjectTypeOf($subject);
        $id = (int) $subject->getKey();
        $provenance = $this->provenance[$type][$id][$field] ?? null;
        $state = $this->state($provenance, ProvenanceWriter::normalise($subject->getAttribute($field)));

        // A value staff set or locked is their decision already.
        if ($state !== 'ok') {
            $this->count($type.'.'.$field.'.uncertain_skipped_'.$state);

            return;
        }

        $this->count($type.'.'.$field.'.uncertain.'.$problem);
        $this->row($type, (string) $id, $field, $problem, $current, (string) $suggestion, 'uncertain', self::PROBLEMS[$problem] ?? '');

        // Many of one kind with a proposal each: one item, one click.
        if (in_array($problem, self::BATCHED, true) && $suggestion !== null) {
            $this->batched[$problem][] = ['subject_type' => $type, 'subject_id' => $id, 'field' => $field, 'current' => $current, 'suggestion' => $suggestion];

            return;
        }

        if (! $this->apply) {
            return;
        }

        $label = $type === 'doctor' ? 'Name' : 'Facility name';
        $label = $field === 'title' ? 'Title' : $label;
        $title = $suggestion !== null
            ? sprintf('%s: „%s“ → „%s“?', $label, $current, $suggestion)
            : sprintf('%s: „%s“', $label, $current);

        $item = ImportReviewItem::raise(self::SOURCE, ImportReviewKind::Uncertain, 'name:'.$type.':'.$id.':'.$field, $title, [
            'reason' => self::REASON,
            'problem' => $problem,
            'field' => $field,
            'current' => $current,
            'suggestion' => $suggestion,
            'action' => (self::PROBLEMS[$problem] ?? $problem).($suggestion !== null ? ' „Прифати предлог“ sets the proposed value.' : ' Edit the profile if it needs a change.'),
        ], $subject, (int) $this->run?->getKey(), (bool) $subject->getAttribute('is_published') ? 20 : 5);

        if ($item->wasRecentlyCreated) {
            $this->count('review_items_created');
        }
    }

    /** Problems raised as one item for all their cases. */
    private const BATCHED = ['latin_script'];

    /** @var array<string, list<array{subject_type: string, subject_id: int, field: string, current: string, suggestion: string}>> */
    private array $batched = [];

    /**
     * One item per batched problem: the list of proposals, accepted (or
     * kept) together.
     */
    private function batchItems(): void
    {
        foreach ($this->batched as $problem => $changes) {
            if (! $this->apply || $changes === []) {
                continue;
            }

            $item = ImportReviewItem::raise(self::SOURCE, ImportReviewKind::Uncertain, 'name:batch:'.$problem, sprintf('%d names: %s', count($changes), self::PROBLEMS[$problem] ?? $problem), [
                'reason' => self::REASON,
                'problem' => $problem,
                'changes' => $changes,
                'proposals' => implode('; ', array_map(fn (array $change): string => $change['current'].' → '.$change['suggestion'], $changes)),
                'action' => (self::PROBLEMS[$problem] ?? $problem).' „Прифати предлог“ sets every proposed value (a profile changed since is skipped); „Остави“ keeps them all.',
            ], null, (int) $this->run?->getKey(), 15);

            if ($item->wasRecentlyCreated) {
                $this->count('review_items_created');
            }
        }
    }

    /** One-line descriptions of the uncertain cases (the review item and the CSV). */
    public const PROBLEMS = [
        'latin_script' => 'Name in Latin script; the directory writes names in Cyrillic.',
        'mixed_script' => 'A Latin letter without a Cyrillic look-alike inside a Cyrillic word.',
        'institution_in_name' => 'An institution or legal form inside a person\'s name.',
        'role_in_name' => 'A profession or title in the middle of the name.',
        'text_after_comma' => 'Text after a comma that is not a title.',
        'name_order' => 'Probably written surname first.',
        'licence_name_differs' => 'The cleaned name would no longer match the Комора licence list.',
        'title_unrecognised' => 'A title the rules do not know.',
    ];

    /**
     * Two-word names written surname first, judged by how often each word
     * is a given name or a surname elsewhere in the directory.
     *
     * @return array{name: string, strong: bool}|null
     */
    private function order(string $name): ?array
    {
        $words = explode(' ', $name);

        if (count($words) !== 2) {
            return null;
        }

        [$first, $last] = array_map(fn (string $word): string => mb_strtoupper($word, 'UTF-8'), $words);
        $firstAsFirst = $this->asFirst[$first] ?? 0;
        $firstAsLast = $this->asLast[$first] ?? 0;
        $lastAsFirst = $this->asFirst[$last] ?? 0;
        $lastAsLast = $this->asLast[$last] ?? 0;

        if (! ($firstAsLast >= 1 && $firstAsFirst * 3 <= $firstAsLast && $lastAsFirst >= 2 && $lastAsLast * 3 <= $lastAsFirst)) {
            return null;
        }

        return [
            'name' => $words[1].' '.$words[0],
            'strong' => $firstAsFirst <= 1 && $firstAsLast >= self::ORDER_SURNAME_MIN && $lastAsFirst >= self::ORDER_GIVEN_MIN && $lastAsLast <= 1,
        ];
    }

    public static function log(Model $subject, string $field, ?string $old, ?string $new, string $category, ?User $by): void
    {
        $entry = activity(self::LOG)
            ->performedOn($subject)
            ->event('name_cleanup')
            ->withProperties(['field' => $field, 'old' => $old, 'new' => $new, 'category' => $category]);

        if ($by !== null) {
            $entry->causedBy($by);
        }

        $entry->log('name_cleanup');
    }

    private function transaction(callable $work): void
    {
        $this->apply ? DB::transaction($work) : $work();
    }

    private function count(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }

    /**
     * @return array<string, int>
     */
    private function sortedCounts(): array
    {
        ksort($this->counts);

        return $this->counts;
    }

    private function row(string $entity, string $id, string $field, string $category, ?string $old, ?string $new, string $action, string $note): void
    {
        $this->rows[] = [$entity, $id, $field, $category, (string) $old, (string) $new, $action, $note];
    }

    /**
     * The private report: every change, skip and uncertain case (names of
     * people — the import disk only, downloadable by staff in Import runs).
     */
    private function writeReport(): void
    {
        if ($this->run === null) {
            return;
        }

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['entity', 'record_id', 'field', 'category', 'old', 'new', 'action', 'note'], ',', '"', '');

        foreach ($this->rows as $row) {
            fputcsv($handle, array_map(self::neutraliseFormula(...), $row), ',', '"', '');
        }

        rewind($handle);
        $path = trim((string) config('import.directory'), '/').'/runs/'.$this->run->getKey().'/name-cleanup.csv';
        Storage::disk((string) config('import.disk'))->put($path, (string) stream_get_contents($handle));
        fclose($handle);

        $this->run->forceFill(['diff_path' => $path]);
    }

    private static function neutraliseFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
