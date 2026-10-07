<?php

namespace App\Support\Verification\Engine;

/**
 * Precision over recall: a doctor profile is verified only when two
 * independent sources agree on the person, or staff checked the identity —
 * except dentists, whom a current ФЗОМ contract verifies alone.
 * Everything else stays unverified, with the first reason that applies.
 * docs/verification.md lists the rules with their evidence.
 */
final class DoctorRules
{
    public function decide(DoctorEvidence $e): Verdict
    {
        if ($e->suppressed) {
            return Verdict::unverified(Reason::SUPPRESSED);
        }

        if ($e->ownerLinked) {
            return Verdict::verified(VerificationRule::OwnerClaim, []);
        }

        $licence = $e->licence;
        $sites = array_values(array_filter($e->websites, fn (WebsiteFact $site): bool => $site->counts()));

        // 1. Official registers: the ФЗОМ contract and the Комора licence.
        if ($e->fzomCurrent && $licence?->holds() && $licence->uniqueFit) {
            return Verdict::verified(VerificationRule::FzomLicence, [
                'source_record_id' => $e->fzomRecordId,
                'komora_licence_id' => $licence->komoraLicenceId,
                'facility_id' => $e->fzomFacilityIds[0] ?? null,
            ]);
        }

        //    The list's namesakes: every profile of this name is backed by a
        //    distinct valid licence that fits it, so whichever is theirs,
        //    the ФЗОМ doctor holds one (no number is attached).
        if ($e->fzomCurrent && $licence === null && $e->namesakeLicences >= 2) {
            return Verdict::verified(VerificationRule::FzomLicence, [
                'source_record_id' => $e->fzomRecordId,
                'namesake_licences' => $e->namesakeLicences,
                'facility_id' => $e->fzomFacilityIds[0] ?? null,
            ]);
        }

        //    Dentists: the ФЗОМ contract alone (there is no public dental
        //    licence list; owner's decision). A licence attached and lapsed
        //    still counts against it. Leaving ФЗОМ removes the verification.
        if ($e->dental && $e->fzomCurrent && ($licence === null || ($licence->onList && $licence->valid))) {
            return Verdict::verified(VerificationRule::FzomDentist, [
                'source_record_id' => $e->fzomRecordId,
                'facility_id' => $e->fzomFacilityIds[0] ?? null,
            ]);
        }

        // 2. The institution's own staff page and a licence nobody else on
        //    the list could hold.
        if ($sites !== [] && $licence?->holds() && $licence->nameUnique) {
            return Verdict::verified(VerificationRule::WebsiteLicence, [
                'source_record_id' => $sites[0]->recordId,
                'facility_id' => $sites[0]->facilityId,
                'komora_licence_id' => $licence->komoraLicenceId,
            ]);
        }

        // 3. ФЗОМ and the staff page of the same institution.
        $agreeing = $e->fzomCurrent ? $this->agreeingSite($e, $sites) : null;

        if ($agreeing !== null) {
            return Verdict::verified(VerificationRule::FzomWebsite, [
                'source_record_id' => $e->fzomRecordId,
                'website_record_id' => $agreeing->recordId,
                'facility_id' => $agreeing->facilityId,
            ]);
        }

        return $this->whyNot($e, $sites);
    }

    /**
     * @param  list<WebsiteFact>  $sites
     */
    private function agreeingSite(DoctorEvidence $e, array $sites): ?WebsiteFact
    {
        foreach ($sites as $site) {
            if (in_array($site->facilityId, $e->fzomFacilityIds, true) && $this->specialtiesAgree($site, $e)) {
                return $site;
            }
        }

        return null;
    }

    /**
     * The staff page states no specialty, or one of ours that ФЗОМ also
     * gives (a dentist's page wording maps to the dental specialty).
     */
    private function specialtiesAgree(WebsiteFact $site, DoctorEvidence $e): bool
    {
        return $site->specialtyIds === null || $site->specialtyIds === []
            || array_intersect($site->specialtyIds, $e->fzomSpecialtyIds) !== [];
    }

    /**
     * @param  list<WebsiteFact>  $sites  the entries that count
     */
    private function whyNot(DoctorEvidence $e, array $sites): Verdict
    {
        $licence = $e->licence;
        $evidence = ['komora_licence_id' => $licence->komoraLicenceId ?? $e->stagedMismatch?->komoraLicenceId];
        $linkedSites = array_values(array_filter($e->websites, fn (WebsiteFact $site): bool => $site->linked));

        return match (true) {
            $licence !== null && ! $licence->onList => Verdict::unverified(Reason::LICENCE_OFF_LIST, $evidence),
            $licence !== null && ! $licence->valid => Verdict::unverified(Reason::LICENCE_EXPIRED, $evidence),
            $e->fzomRecordId !== null && ! $e->fzomCurrent && $sites === [] => Verdict::unverified(Reason::SOURCE_REMOVED, ['source_record_id' => $e->fzomRecordId]),
            $licence !== null && ! $licence->nameAgrees => Verdict::unverified(Reason::NAME_MISMATCH, $evidence),
            (($licence !== null && ! $licence->fits) || $e->stagedMismatch !== null) && $e->specialtyNames === [] => Verdict::unverified(Reason::NO_SPECIALTY, $evidence),
            ($licence !== null && ! $licence->fits) || $e->stagedMismatch !== null => Verdict::unverified(Reason::SPECIALTY_MISMATCH, $evidence),
            ($licence !== null || $e->licenceAmbiguous) && ($e->fzomCurrent || $sites !== []) => Verdict::unverified(Reason::AMBIGUOUS_NAME, $evidence),
            $sites === [] && array_filter($linkedSites, fn (WebsiteFact $site): bool => $site->isFlagged()) !== [] => Verdict::unverified(Reason::STALE_SOURCE),
            $sites === [] && $linkedSites !== [] => Verdict::unverified(Reason::LOW_CONFIDENCE_SOURCE),
            $e->fzomCurrent && $sites !== [] => Verdict::unverified(Reason::SOURCES_DISAGREE),
            $e->dental && ($e->fzomCurrent || $sites !== []) => Verdict::unverified(Reason::DENTIST_SINGLE_SOURCE),
            $e->fzomCurrent || $sites !== [] => Verdict::unverified(Reason::NO_LICENCE),
            $e->fzomRecordId !== null => Verdict::unverified(Reason::SOURCE_REMOVED, ['source_record_id' => $e->fzomRecordId]),
            default => Verdict::unverified(Reason::NO_IMPORT_EVIDENCE),
        };
    }
}
