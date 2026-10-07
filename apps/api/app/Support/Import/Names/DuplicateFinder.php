<?php

namespace App\Support\Import\Names;

use App\Support\Import\NameKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Profiles that look like one person entered twice by different sources:
 * the same normalised name (either word order).
 *
 * Nothing is merged here, and nothing is merged automatically: two profiles
 * are the same person for certain only when they share a licence number or
 * a ФЗО facsimile, and both are unique per profile in the database, so that
 * never happens. What it proposes, grouped so one decision settles many:
 *
 * - `website_into_register` — a hidden website draft (never published, no
 *   owner, no reviews, no licence) and exactly one ФЗОМ profile of that
 *   name: the draft can be merged into the ФЗОМ profile (DoctorMerger).
 *   Grouped by the institution whose staff page listed the drafts (the
 *   title says how many of the pairs name different towns);
 * - `website_twins` — website profiles of one name in one town and no ФЗОМ
 *   profile (one person on two sites): the drafts merge into the one that
 *   is public, or into the oldest draft;
 * - `namesakes` — any other group with a published website profile, or
 *   ФЗОМ profiles that share a workplace: listed for a person to compare,
 *   no merge offered (namesakes are real). Hidden website drafts beside
 *   several ФЗОМ namesakes are left alone: they stay hidden drafts.
 *
 * ФЗОМ profiles of one name without a shared workplace are different
 * people (different facsimiles) and are not reported.
 */
final class DuplicateFinder
{
    public const REASON = 'possible_duplicate';

    /**
     * @return list<array{kind: string, key: string, title: string, details: array<string, mixed>, facility_id: int|null, priority: int, doctor_ids: list<int>, label: string}>
     */
    public function find(): array
    {
        $doctors = DB::table('doctors')
            ->whereNull('deleted_at')
            ->whereNotNull('name_key_sorted')
            ->where('name_key_sorted', '!=', '')
            ->whereIn('name_key_sorted', DB::table('doctors')->whereNull('deleted_at')->select('name_key_sorted')->groupBy('name_key_sorted')->havingRaw('count(*) > 1'))
            ->orderBy('id')
            ->get(['id', 'full_name', 'name_key_sorted', 'import_source', 'is_published', 'published_at', 'owner_user_id', 'reviews_count', 'licence_number', 'city']);

        if ($doctors->isEmpty()) {
            return [];
        }

        $links = DB::table('doctor_facility')
            ->whereIn('doctor_id', $doctors->pluck('id'))
            ->orderByDesc('is_primary')->orderBy('facility_id')
            ->get(['doctor_id', 'facility_id'])
            ->groupBy('doctor_id')
            ->map(fn (Collection $rows): array => $rows->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
        $facilityNames = DB::table('facilities')->whereIn('id', $links->flatten()->unique()->values())->pluck('name', 'id');

        $pairs = [];
        $other = [];

        foreach ($doctors->groupBy('name_key_sorted') as $key => $group) {
            $fzom = $group->filter(fn ($row): bool => $row->import_source === 'fzom')->values();
            $drafts = $group->filter(fn ($row): bool => self::mergeableDraft($row))->values();

            if ($fzom->count() === 1 && $drafts->isNotEmpty()) {
                $target = $fzom->first();

                foreach ($drafts as $draft) {
                    $pairs[] = ['kind' => 'website_into_register', 'from' => $draft, 'into' => $target, 'same_town' => self::town($draft->city) === self::town($target->city)];
                }

                continue;
            }

            // Website profiles only, one town, at most one of them public (or
            // otherwise not a plain draft): the drafts go into that one, or
            // into the oldest draft.
            $kept = $group->reject(fn ($row): bool => self::mergeableDraft($row))->values();

            if ($fzom->isEmpty() && $group->every(fn ($row): bool => $row->import_source === 'website') && $kept->count() <= 1
                && $group->map(fn ($row): string => self::town($row->city))->unique()->count() === 1) {
                $target = $kept->first() ?? $drafts->first();

                foreach ($drafts as $draft) {
                    if ($draft->id !== $target->id) {
                        $pairs[] = ['kind' => 'website_twins', 'from' => $draft, 'into' => $target, 'same_town' => true];
                    }
                }

                continue;
            }

            // Hidden website drafts beside several ФЗОМ namesakes stay hidden
            // drafts (never verified: the licence could be either's), so
            // only a public one, or ФЗОМ profiles sharing a workplace, is a
            // question worth asking.
            $publicWebsite = $group->contains(fn ($row): bool => $row->import_source === 'website' && (bool) $row->is_published);
            $sharedWorkplace = $fzom->count() >= 2 && self::shareFacility($fzom->pluck('id')->all(), $links->all());

            if ($publicWebsite || $sharedWorkplace) {
                $other[(string) $key] = $group;
            }
        }

        return [...$this->mergeGroups($pairs, $links->all(), $facilityNames->all()), ...$this->namesakeGroups($other)];
    }

    /**
     * @param  list<array{kind: string, from: object, into: object, same_town: bool}>  $pairs
     * @param  array<int, list<int>>  $links
     * @param  array<int, string>  $facilityNames
     * @return list<array{kind: string, key: string, title: string, details: array<string, mixed>, facility_id: int|null, priority: int, doctor_ids: list<int>, label: string}>
     */
    private function mergeGroups(array $pairs, array $links, array $facilityNames): array
    {
        $grouped = [];

        foreach ($pairs as $pair) {
            $facilityId = $links[(int) $pair['from']->id][0] ?? null;
            $groupKey = $pair['kind'].':'.($facilityId ?? 0);
            $grouped[$groupKey] ??= ['kind' => $pair['kind'], 'facility_id' => $facilityId, 'pairs' => [], 'elsewhere' => 0];
            $grouped[$groupKey]['pairs'][] = [(int) $pair['from']->id, (int) $pair['into']->id];
            $grouped[$groupKey]['elsewhere'] += $pair['same_town'] ? 0 : 1;
        }

        $groups = [];

        foreach ($grouped as $groupKey => $group) {
            $count = count($group['pairs']);
            $elsewhere = $group['elsewhere'];
            $facility = $group['facility_id'] !== null ? ($facilityNames[$group['facility_id']] ?? '#'.$group['facility_id']) : 'no workplace';
            $title = $group['kind'] === 'website_into_register'
                ? sprintf('Possible duplicates: %d website draft(s) of „%s“ have the name of a ФЗОМ doctor%s', $count, $facility, $elsewhere > 0 ? sprintf(' (%d in another town)', $elsewhere) : '')
                : sprintf('Possible duplicates: %d website draft(s) of „%s“ have the name of another website profile in the same town', $count, $facility);
            usort($group['pairs'], fn (array $a, array $b): int => $a <=> $b);

            $groups[] = [
                'kind' => $group['kind'],
                'key' => 'duplicates:'.$groupKey,
                'title' => $title,
                'details' => [
                    'reason' => self::REASON,
                    'kind' => $group['kind'],
                    'pairs' => $group['pairs'],
                    'count' => $count,
                    'other_town' => $elsewhere,
                    'action' => $group['kind'] === 'website_into_register'
                        ? 'Each draft has the name of exactly one ФЗОМ doctor in the whole register'.($elsewhere > 0 ? " ({$elsewhere} of them contracted in another town)" : '').'. „Спои ги“ moves each draft\'s workplace (and title, specialties when the profile has none) into the ФЗОМ profile and deletes the draft; „Остави“ keeps them apart.'
                        : 'One person listed on two sites? „Спои ги“ moves each hidden draft\'s workplace into the other profile (the public one, or the oldest draft) and deletes the draft; „Остави“ keeps them apart.',
                ],
                'facility_id' => $group['facility_id'],
                // Big groups first; mostly same-town ones before the rest.
                'priority' => min(99, $count + ($elsewhere * 2 <= $count ? 10 : 0)),
                'doctor_ids' => array_values(array_unique(array_merge(...$group['pairs']))),
                'label' => $title,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, Collection<int, \stdClass>>  $groups
     * @return list<array{kind: string, key: string, title: string, details: array<string, mixed>, facility_id: int|null, priority: int, doctor_ids: list<int>, label: string}>
     */
    private function namesakeGroups(array $groups): array
    {
        $result = [];

        foreach ($groups as $key => $group) {
            $ids = $group->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $name = (string) $group->first()->full_name;

            $result[] = [
                'kind' => 'namesakes',
                'key' => 'duplicates:namesakes:'.sha1($key),
                'title' => sprintf('Same name, %d profiles: %s', count($ids), $name),
                'details' => [
                    'reason' => self::REASON,
                    'kind' => 'namesakes',
                    'candidate_doctor_ids' => $ids,
                    'action' => 'Several profiles of this name (namesakes are possible): compare them; merge by hand only if they are one person, or „Остави“.',
                ],
                'facility_id' => null,
                'priority' => 1,
                'doctor_ids' => $ids,
                'label' => 'namesakes',
            ];
        }

        return $result;
    }

    /**
     * A website draft nobody has touched in public: never published, no
     * owner, no reviews, no licence attached.
     */
    public static function mergeableDraft(object $row): bool
    {
        return $row->import_source === 'website'
            && ! (bool) $row->is_published
            && $row->published_at === null
            && $row->owner_user_id === null
            && (int) $row->reviews_count === 0
            && $row->licence_number === null;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, list<int>>  $links
     */
    private static function shareFacility(array $ids, array $links): bool
    {
        $seen = [];

        foreach ($ids as $id) {
            foreach ($links[$id] ?? [] as $facilityId) {
                if (isset($seen[$facilityId])) {
                    return true;
                }

                $seen[$facilityId] = true;
            }
        }

        return false;
    }

    private static function town(?string $city): string
    {
        return NameKey::for((string) (preg_split('/\s+[-–—]\s+/u', trim((string) $city))[0] ?? ''));
    }
}
