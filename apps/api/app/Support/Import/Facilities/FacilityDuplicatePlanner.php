<?php

namespace App\Support\Import\Facilities;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\Website\InstitutionsJsonImporter;
use Illuminate\Support\Facades\DB;

/**
 * Turns FacilityDuplicateMatcher's proposals into a plan (read-only: what
 * would be merged, what staff would be asked, how many published doctors
 * would get a visible workplace) and applies it: high-confidence merges
 * through FacilityMerger, every other proposal as a review item
 * (`facility_duplicate`) with its candidates.
 */
final class FacilityDuplicatePlanner
{
    public const REASON = 'facility_duplicate';

    public const ITEM_PREFIX = 'facility-duplicate:';

    public function __construct(
        private readonly FacilityDuplicateMatcher $matcher,
        private readonly FacilityMerger $merger,
    ) {}

    /**
     * @param  list<int>|null  $only  website facility ids (null: all website-only facilities)
     * @return array{generated_at: string, counts: array<string, int>, high: list<array<string, mixed>>, medium: list<array<string, mixed>>, low: list<array<string, mixed>>, none: list<array<string, mixed>>}
     */
    public function plan(?array $only = null): array
    {
        $this->matcher->load();
        $ids = FacilityDuplicateMatcher::websiteOnlyIds();

        if ($only !== null) {
            $ids = array_values(array_intersect($ids, $only));
        }

        $plan = ['generated_at' => now()->toIso8601String(), 'counts' => [], 'high' => [], 'medium' => [], 'low' => [], 'none' => []];
        $visible = $this->doctorsWithVisibleWorkplace();
        $gain = ['high' => [], 'medium' => [], 'low' => []];
        $stranded = [];

        foreach (array_chunk($ids, 200) as $chunk) {
            $links = DB::table('doctor_facility')
                ->join('doctors', 'doctors.id', '=', 'doctor_facility.doctor_id')
                ->whereIn('doctor_facility.facility_id', $chunk)
                ->whereNull('doctors.deleted_at')
                ->get(['doctor_facility.facility_id', 'doctors.id', 'doctors.is_published'])
                ->groupBy('facility_id');

            foreach (Facility::query()->whereIn('id', $chunk)->orderBy('id')->get() as $facility) {
                $proposal = $this->matcher->match($facility);
                $doctors = $links->get($facility->getKey(), collect());
                $published = $doctors->where('is_published', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $noWorkplace = array_values(array_filter($published, fn (int $id): bool => ! isset($visible[$id])));
                $target = $proposal['suggested_facility_id'] !== null ? Facility::query()->find($proposal['suggested_facility_id']) : null;

                foreach ($noWorkplace as $id) {
                    $stranded[$id] = true;
                }

                $proposal['doctors'] = $doctors->count();
                $proposal['published_doctors'] = count($published);
                $proposal['published_doctors_without_visible_workplace'] = count($noWorkplace);
                $proposal['suggested_target_published'] = (bool) $target?->is_published;
                $proposal['refusal'] = $target !== null ? $this->merger->refusal($facility, $target) : null;

                $level = $proposal['confidence'] ?? 'none';

                if ($level !== 'none' && $target !== null && $target->is_published && $target->type !== FacilityType::Pharmacy) {
                    foreach ($noWorkplace as $id) {
                        $gain[$level][$id] = true;
                    }
                }

                $plan[$level][] = $proposal;
            }
        }

        $high = $gain['high'];
        $upToMedium = $high + $gain['medium'];

        $plan['counts'] = [
            'website_only_facilities' => count($ids),
            'high' => count($plan['high']),
            'medium' => count($plan['medium']),
            'low' => count($plan['low']),
            'no_candidate' => count($plan['none']),
            'high_refused' => count(array_filter($plan['high'], fn (array $p): bool => $p['refusal'] !== null)),
            'doctor_links_moved_by_high' => array_sum(array_column($plan['high'], 'doctors')),
            'published_doctors_without_visible_workplace' => count($stranded),
            'would_get_visible_workplace_high' => count($high),
            'would_get_visible_workplace_high_and_medium' => count($upToMedium),
            'would_get_visible_workplace_any_candidate' => count($upToMedium + $gain['low']),
        ];

        return $plan;
    }

    /**
     * Applies a plan: merges its high-confidence proposals (each checked
     * again first: still high, same target — otherwise queued), queues the
     * rest. Repeating it changes nothing (merges are idempotent; open items
     * are refreshed, dismissed ones stay dismissed).
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, int>
     */
    public function apply(array $plan, User $by, bool $mergeHigh = true): array
    {
        $result = ['merged' => 0, 'already_merged' => 0, 'refused' => 0, 'changed_since_plan' => 0, 'queued' => 0];
        $this->matcher->load();

        foreach ((array) ($plan['high'] ?? []) as $proposal) {
            if (! $mergeHigh) {
                $result['queued'] += (int) $this->queue($proposal);

                continue;
            }

            $outcome = $this->mergeIfStillHigh((int) $proposal['facility_id'], (int) $proposal['suggested_facility_id'], $by);

            if ($outcome === null) {
                $result['changed_since_plan']++;
                $fresh = $this->freshProposal((int) $proposal['facility_id']);
                $result['queued'] += (int) ($fresh !== null && $this->queue($fresh));

                continue;
            }

            $result[$outcome->status]++;
        }

        foreach (['medium', 'low'] as $level) {
            foreach ((array) ($plan[$level] ?? []) as $proposal) {
                $result['queued'] += (int) $this->queue($proposal);
            }
        }

        return $result;
    }

    /**
     * Merges when the matcher, run now, still proposes this target with
     * high confidence; null when it does not (the data changed since the
     * plan was made). An earlier merge of the pair counts as done.
     */
    public function mergeIfStillHigh(int $fromId, int $intoId, User $by): ?FacilityMergeResult
    {
        $from = Facility::withTrashed()->find($fromId);

        if ($from !== null && $from->trashed() && (int) $from->getAttribute('merged_into_id') === $intoId) {
            return FacilityMergeResult::alreadyMerged();
        }

        $fresh = $this->freshProposal($fromId);

        if ($fresh === null || $fresh['confidence'] !== FacilityDuplicateMatcher::HIGH || (int) $fresh['suggested_facility_id'] !== $intoId) {
            return null;
        }

        return $this->merger->merge($fromId, $intoId, $by, 'matcher_high');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function freshProposal(int $fromId): ?array
    {
        $facility = Facility::query()->find($fromId);

        if ($facility === null || $facility->import_source !== InstitutionsJsonImporter::SOURCE) {
            return null;
        }

        return $this->matcher->match($facility);
    }

    /**
     * Raises (or refreshes) the review item of a proposal with candidates.
     *
     * @param  array<string, mixed>  $proposal
     */
    public function queue(array $proposal): bool
    {
        $facility = Facility::query()->find($proposal['facility_id'] ?? null);
        $candidates = array_values(array_map(fn (array $candidate): array => [
            'facility_id' => (int) $candidate['facility_id'],
            'name' => (string) $candidate['name'],
            'city' => $candidate['city'],
            'address' => $candidate['address'],
            'confidence' => (string) $candidate['confidence'],
            'reasons' => implode('; ', (array) $candidate['reasons']),
        ], (array) ($proposal['candidates'] ?? [])));

        if ($facility === null || $candidates === []) {
            return false;
        }

        $first = $candidates[0];
        $item = ImportReviewItem::raise(
            InstitutionsJsonImporter::SOURCE,
            ImportReviewKind::Uncertain,
            self::ITEM_PREFIX.$facility->getKey(),
            'Same facility? '.$facility->name.' → '.$first['name'],
            [
                'reason' => self::REASON,
                'confidence' => (string) ($proposal['confidence'] ?? $first['confidence']),
                'suggested_facility_id' => $first['facility_id'],
                'suggested_name' => $first['name'],
                'reasons' => implode('; ', (array) ($proposal['reasons'] ?? [])),
                'candidates' => $candidates,
                'action' => '“Merge into suggested facility” moves the doctors, the website images and the empty-field values of this website-only draft to the register facility and retires the draft (restorable); future imports of the site update the register facility. “Merge into register facility…” lets you pick another one. Dismiss keeps them apart.',
            ],
            $facility,
            null,
            (int) ($proposal['doctors'] ?? 0),
        );

        return $item->wasRecentlyCreated;
    }

    /**
     * Published doctors who already show a workplace (a published,
     * clinical, not deleted facility).
     *
     * @return array<int, true>
     */
    private function doctorsWithVisibleWorkplace(): array
    {
        $visible = [];
        $ids = DB::table('doctor_facility')
            ->join('facilities', 'facilities.id', '=', 'doctor_facility.facility_id')
            ->where('facilities.is_published', true)
            ->whereNull('facilities.deleted_at')
            ->whereIn('facilities.type', FacilityType::clinicalValues())
            ->distinct()
            ->pluck('doctor_facility.doctor_id');

        foreach ($ids as $id) {
            $visible[(int) $id] = true;
        }

        return $visible;
    }
}
