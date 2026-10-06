<?php

namespace App\Support\Licences;

use App\Support\Import\Contracts\LicenceReviewReason;
use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidate;
use App\Support\Licences\Contracts\LicenceCandidateSource;

/**
 * Decides, for each licence row, which doctor profile it belongs to — or
 * that staff must decide. It never guesses:
 *
 * - a licence number already on a profile stays with that profile (its
 *   expiry is refreshed);
 * - otherwise the candidates are the imported profiles with the same
 *   normalised name (NameKey) that carry no other licence, and the row's
 *   specialty must fit theirs through the mapping (LicenceSpecialtyMap);
 * - exactly one fitting candidate → attach; several → Ambiguous; none with
 *   the name → NoMatch; the name but not the specialty (or a specialty
 *   nobody has mapped yet) → SpecialtyMismatch;
 * - and the list's own namesakes: when one profile would take two licence
 *   rows (two doctors of the same name and a fitting specialty on the list),
 *   neither is attached — both go to review as Ambiguous.
 *
 * Expiry plays no part in the decision: an expired licence is still
 * attached (the profile then shows it as not valid) and is never a reason to
 * unpublish anything here.
 */
final class KomoraLicenceMatcher
{
    public function __construct(
        private readonly LicenceCandidateSource $source,
        private readonly LicenceSpecialtyMap $specialties,
    ) {}

    /**
     * @param  list<ParsedLicenceRow>  $rows
     * @return list<LicenceDecision>
     */
    public function decide(array $rows): array
    {
        /** @var array<string, list<LicenceCandidate>> $candidatesByName */
        $candidatesByName = [];
        $decisions = [];

        foreach ($rows as $index => $row) {
            $holder = $this->source->doctorIdForLicence($row->licenceNumber);

            if ($holder !== null) {
                $decisions[$index] = LicenceDecision::attach($row, $holder, alreadyAttached: true);

                continue;
            }

            $nameKey = NameKey::for($row->fullName);
            $candidatesByName[$nameKey] ??= $this->source->candidatesFor($row->fullName);

            $candidates = array_values(array_filter(
                $candidatesByName[$nameKey],
                fn (LicenceCandidate $candidate): bool => $candidate->licenceNumber === null || $candidate->licenceNumber === $row->licenceNumber,
            ));
            $candidateIds = array_map(fn (LicenceCandidate $candidate): int => $candidate->doctorId, $candidates);

            if ($candidates === []) {
                $decisions[$index] = LicenceDecision::review($row, LicenceReviewReason::NoMatch);

                continue;
            }

            $groups = $this->specialties->licenceGroups($row->specialty);

            if ($groups === null) {
                $decisions[$index] = LicenceDecision::review($row, LicenceReviewReason::SpecialtyMismatch, $candidateIds);

                continue;
            }

            $fitting = array_values(array_filter(
                $candidates,
                fn (LicenceCandidate $candidate): bool => $this->specialties->fits($groups, $candidate),
            ));

            $decisions[$index] = match (count($fitting)) {
                0 => LicenceDecision::review($row, LicenceReviewReason::SpecialtyMismatch, $candidateIds),
                1 => LicenceDecision::attach($row, $fitting[0]->doctorId),
                default => LicenceDecision::review(
                    $row,
                    LicenceReviewReason::Ambiguous,
                    array_map(fn (LicenceCandidate $candidate): int => $candidate->doctorId, $fitting),
                ),
            };
        }

        return array_values($this->withoutSharedTargets($decisions));
    }

    /**
     * Two new rows that would land on the same profile are namesakes on the
     * list; neither is attached.
     *
     * @param  array<int, LicenceDecision>  $decisions
     * @return array<int, LicenceDecision>
     */
    private function withoutSharedTargets(array $decisions): array
    {
        $rowsByTarget = [];

        foreach ($decisions as $index => $decision) {
            if ($decision->attaches() && ! $decision->alreadyAttached) {
                $rowsByTarget[$decision->doctorId][] = $index;
            }
        }

        foreach ($rowsByTarget as $doctorId => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $decisions[$index] = LicenceDecision::review($decisions[$index]->row, LicenceReviewReason::Ambiguous, [(int) $doctorId]);
            }
        }

        ksort($decisions);

        return $decisions;
    }
}
