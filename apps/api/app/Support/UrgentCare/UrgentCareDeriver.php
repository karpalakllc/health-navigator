<?php

namespace App\Support\UrgentCare;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Support\Import\ImportContext;
use Illuminate\Support\Facades\DB;

/**
 * Turns the urgent-care evidence in imported data into facility flags
 * (docs/urgent-care.md § Data). Runs at the end of every ФЗОМ and website
 * import, and by hand: `php artisan urgent-care:derive [--dry-run]`.
 *
 * Rules:
 * - Only `strong` evidence (UrgentCareClassifier) switches a flag on; a
 *   `candidate` is only listed for staff (the admin's „Urgent care: to
 *   check“ filter).
 * - A flag is never switched off here.
 * - Staff win: once staff confirmed the facility (urgent_care_checked_at), its
 *   flags are not touched again; and a flag this deriver set once that is off
 *   now was switched off by staff, so it stays off.
 * - Hours are never written; is_open_24h only from a clause in which the
 *   institution itself ties 24/7 to the urgent service.
 *
 * Writes go straight to the table (no model events): like the imports, this
 * does not write one activity-log entry per facility.
 */
final class UrgentCareDeriver
{
    /**
     * @return array{facilities_with_evidence: int, flags_set: int, kept_off_by_staff: int, confirmed_by_staff: int, candidates: int, set: list<array{facility_id: int, name: string, city: string|null, flags: list<string>}>}
     */
    public function run(?ImportContext $context = null, bool $write = true): array
    {
        $summary = [
            'facilities_with_evidence' => 0,
            'flags_set' => 0,
            'kept_off_by_staff' => 0,
            'confirmed_by_staff' => 0,
            'candidates' => 0,
            'set' => [],
        ];

        Facility::query()
            ->where('type', '<>', FacilityType::Pharmacy->value)
            ->select(['id', 'name', 'city', 'urgent_care_evidence', 'urgent_care_checked_at', ...array_values(UrgentCareClassifier::COLUMNS)])
            ->chunkById(500, function ($facilities) use (&$summary, $context, $write): void {
                $ids = $facilities->modelKeys();
                $texts = $this->texts($ids);

                foreach ($facilities as $facility) {
                    $items = UrgentCareClassifier::classify((string) $facility->name, $texts[$facility->getKey()] ?? []);
                    $previous = is_array($facility->urgent_care_evidence) ? $facility->urgent_care_evidence : [];

                    if ($items === [] && $previous === []) {
                        continue;
                    }

                    $derived = array_values(array_filter((array) ($previous['derived'] ?? []), 'is_string'));
                    $updates = [];
                    $setNow = [];

                    if ($items !== []) {
                        $summary['facilities_with_evidence']++;
                    }

                    $summary['candidates'] += count(array_filter($items, fn (array $item): bool => $item['strength'] === UrgentCareClassifier::CANDIDATE));

                    foreach (UrgentCareClassifier::strongFlags($items) as $flag) {
                        $column = UrgentCareClassifier::COLUMNS[$flag];

                        if ((bool) $facility->getAttribute($column)) {
                            continue;
                        }

                        if ($facility->urgent_care_checked_at !== null) {
                            $summary['confirmed_by_staff']++;

                            continue;
                        }

                        if (in_array($flag, $derived, true)) {
                            $summary['kept_off_by_staff']++;

                            continue;
                        }

                        $updates[$column] = true;
                        $derived[] = $flag;
                        $setNow[] = $flag;
                    }

                    $evidence = [
                        'items' => $items,
                        'derived' => array_values(array_unique($derived)),
                        'strong' => UrgentCareClassifier::strongFlags($items),
                        'has_candidates' => array_filter($items, fn (array $item): bool => $item['strength'] === UrgentCareClassifier::CANDIDATE) !== [],
                    ];
                    $sameEvidence = self::withoutTime($previous) === $evidence;

                    if ($setNow === [] && $sameEvidence) {
                        continue;
                    }

                    $evidence['derived_at'] = now()->toDateTimeString();
                    $updates['urgent_care_evidence'] = json_encode($evidence, JSON_UNESCAPED_UNICODE);

                    if ($setNow !== []) {
                        $summary['flags_set'] += count($setNow);
                        $summary['set'][] = ['facility_id' => (int) $facility->getKey(), 'name' => (string) $facility->name, 'city' => $facility->city, 'flags' => $setNow];
                        $context?->increment('urgent_care_flags_set', count($setNow));
                        $context?->record('facility', 'update', $facility, (string) $facility->name, 'urgent_care', null, implode(',', $setNow), 'Urgent care derived from the source wording.');
                    }

                    if ($write) {
                        DB::table('facilities')->where('id', $facility->getKey())->update($updates);
                    }
                }
            });

        return $summary;
    }

    /**
     * Work units (ФЗОМ), departments and hours text (websites) and published
     * department names, per facility.
     *
     * @param  list<int|string>  $ids
     * @return array<int, list<array{source: string, kind: string, text: string}>>
     */
    private function texts(array $ids): array
    {
        $texts = [];

        DB::table('doctor_facility')
            ->whereIn('facility_id', $ids)
            ->whereNotNull('work_unit')
            ->select(['facility_id', 'work_unit', 'source'])
            ->distinct()
            ->get()
            ->each(function ($row) use (&$texts): void {
                $texts[(int) $row->facility_id][] = ['source' => (string) ($row->source ?? 'staff'), 'kind' => 'work_unit', 'text' => (string) $row->work_unit];
            });

        DB::table('department_facility')
            ->join('departments', 'departments.id', '=', 'department_facility.department_id')
            ->whereIn('department_facility.facility_id', $ids)
            ->select(['department_facility.facility_id', 'departments.name'])
            ->get()
            ->each(function ($row) use (&$texts): void {
                $texts[(int) $row->facility_id][] = ['source' => 'directory', 'kind' => 'department', 'text' => (string) $row->name];
            });

        DB::table('source_records')
            ->where('source', 'website')
            ->where('subject_type', 'facility')
            ->whereIn('subject_id', $ids)
            ->select(['subject_id', 'payload'])
            ->get()
            ->each(function ($row) use (&$texts): void {
                $payload = json_decode((string) $row->payload, true);

                if (! is_array($payload)) {
                    return;
                }

                foreach ((array) ($payload['departments'] ?? []) as $department) {
                    if (is_string($department)) {
                        $texts[(int) $row->subject_id][] = ['source' => 'website', 'kind' => 'department', 'text' => $department];
                    }
                }

                if (is_string($payload['hours'] ?? null)) {
                    $texts[(int) $row->subject_id][] = ['source' => 'website', 'kind' => 'hours', 'text' => $payload['hours']];
                }
            });

        return $texts;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private static function withoutTime(array $evidence): array
    {
        unset($evidence['derived_at']);

        return $evidence;
    }
}
