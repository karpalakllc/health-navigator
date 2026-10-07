<?php

namespace App\Support\Licences;

use App\Models\LicenceSpecialtyMapping;
use App\Support\Licences\Contracts\LicenceCandidate;

/**
 * The licence specialty mapping, loaded once per run.
 *
 * A licence row's specialty gives the groups it fits (its own group and the
 * compatible ones). A candidate profile's groups come from its specialties:
 * a mapping row linked to the specialty, the specialty's name read as either
 * source's wording (imported specialties carry the ФЗОМ wording), or a slug
 * that is itself a group key.
 */
final class LicenceSpecialtyMap
{
    /** @var array<string, array<string, LicenceSpecialtyMapping>> source → key → row */
    private array $byKey = [];

    /** @var array<int, list<string>> specialty id → groups */
    private array $bySpecialty = [];

    /** @var array<string, true> */
    private array $groups = [];

    /**
     * @param  iterable<LicenceSpecialtyMapping>|null  $mappings  null loads the table
     */
    public function __construct(?iterable $mappings = null)
    {
        foreach ($mappings ?? LicenceSpecialtyMapping::query()->get() as $mapping) {
            $this->byKey[$mapping->source][$mapping->source_key] = $mapping;

            if ($mapping->group_key !== null && ! $mapping->is_ignored) {
                $this->groups[$mapping->group_key] = true;

                if ($mapping->specialty_id !== null) {
                    $this->bySpecialty[$mapping->specialty_id][] = $mapping->group_key;
                }
            }
        }
    }

    /**
     * The groups a licence with this specialty fits, or null when the
     * wording is unknown, unmapped or ignored (staff must look).
     *
     * @return list<string>|null
     */
    public function licenceGroups(?string $specialty): ?array
    {
        if ($specialty === null || trim($specialty) === '') {
            return null;
        }

        $mapping = $this->byKey[LicenceSpecialtyMapping::SOURCE_KOMORA][SpecialtyKey::for($specialty)] ?? null;

        if ($mapping === null || $mapping->is_ignored || $mapping->group_key === null) {
            return null;
        }

        return array_values(array_unique([$mapping->group_key, ...($mapping->compatible_groups ?? [])]));
    }

    /**
     * Whether the Комора wording is in the table at all (mapped or not).
     */
    public function knowsLicenceSpecialty(string $specialty): bool
    {
        return isset($this->byKey[LicenceSpecialtyMapping::SOURCE_KOMORA][SpecialtyKey::for($specialty)]);
    }

    /**
     * @return list<string>
     */
    public function candidateGroups(LicenceCandidate $candidate): array
    {
        $groups = [];

        foreach ($candidate->specialtyIds as $id) {
            foreach ($this->bySpecialty[$id] ?? [] as $group) {
                $groups[$group] = true;
            }
        }

        foreach ($candidate->specialtyNames as $name) {
            foreach (SpecialtyKey::splitList($name) as $item) {
                $key = SpecialtyKey::for($item);

                foreach ([LicenceSpecialtyMapping::SOURCE_FZOM, LicenceSpecialtyMapping::SOURCE_KOMORA] as $source) {
                    $mapping = $this->byKey[$source][$key] ?? null;

                    if ($mapping !== null && ! $mapping->is_ignored && $mapping->group_key !== null) {
                        $groups[$mapping->group_key] = true;
                    }
                }
            }
        }

        foreach ($candidate->specialtySlugs as $slug) {
            if (isset($this->groups[$slug])) {
                $groups[$slug] = true;
            }
        }

        return array_keys($groups);
    }

    /**
     * Whether a licence with these groups fits the profile: they share a
     * group — or the profile has no specialty at all and the licence is a
     * general doctor's (config licences.match.general_group), which says
     * "no specialisation" too. Not for a website-only draft: its page may
     * state a specialty nobody has mapped yet, which a general licence
     * would contradict.
     *
     * @param  list<string>  $licenceGroups
     */
    public function fits(array $licenceGroups, LicenceCandidate $candidate): bool
    {
        if ($candidate->specialtyIds === [] && $candidate->specialtyNames === [] && $candidate->specialtySlugs === []) {
            return ! $candidate->fallback && $this->isGeneral($licenceGroups);
        }

        return array_intersect($licenceGroups, $this->candidateGroups($candidate)) !== [];
    }

    /**
     * A general doctor's licence (its own group is the general group).
     *
     * @param  list<string>  $licenceGroups  as licenceGroups() returns them: own group first
     */
    public function isGeneral(array $licenceGroups): bool
    {
        $general = config('licences.match.general_group');

        return is_string($general) && $general !== '' && ($licenceGroups[0] ?? null) === $general;
    }
}
