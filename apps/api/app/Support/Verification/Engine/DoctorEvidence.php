<?php

namespace App\Support\Verification\Engine;

/**
 * Everything the sources say about one doctor profile, loaded in bulk by
 * EvidenceLoader. Plain facts; DoctorRules decides.
 */
final readonly class DoctorEvidence
{
    /**
     * @param  list<int>  $fzomFacilityIds  workplaces the ФЗОМ import linked
     * @param  list<int>  $fzomSpecialtyIds  specialties the ФЗОМ import linked
     * @param  LicenceFacts|null  $licence  the licence attached to the profile, as on the list
     * @param  LicenceFacts|null  $stagedMismatch  an unattached list row with the profile's name (only this profile)
     *                                             whose specialty does not fit
     * @param  bool  $licenceAmbiguous  an unattached list row the matcher could not give to one of several profiles, this one included
     * @param  int  $namesakeLicences  when the list has several valid licences of this name that fit the profile, none
     *                                 attached, and at least as many as there are profiles of the name: how many (else 0)
     * @param  list<WebsiteFact>  $websites  staff-page entries that point at this profile
     * @param  list<string>  $specialtyNames  the profile's specialties (for staff-facing review items)
     * @param  bool  $fzomStale  the ФЗОМ register is older than the maximum age (nothing from it is current)
     */
    public function __construct(
        public int $doctorId,
        public bool $published,
        public int $reviewsCount,
        public bool $suppressed,
        public bool $ownerLinked,
        public ?int $fzomRecordId,
        public bool $fzomCurrent,
        public array $fzomFacilityIds,
        public array $fzomSpecialtyIds,
        public bool $dental,
        public ?LicenceFacts $licence,
        public ?LicenceFacts $stagedMismatch,
        public bool $licenceAmbiguous,
        public int $namesakeLicences,
        public array $websites,
        public array $specialtyNames = [],
        public bool $fzomStale = false,
    ) {}

    /**
     * The same facts with every flagged website trusted: would that verify?
     * (The engine asks once per flagged site whether staff trust it.)
     */
    public function withFlaggedSitesTrusted(): self
    {
        return new self(
            $this->doctorId, $this->published, $this->reviewsCount, $this->suppressed, $this->ownerLinked,
            $this->fzomRecordId, $this->fzomCurrent, $this->fzomFacilityIds, $this->fzomSpecialtyIds, $this->dental,
            $this->licence, $this->stagedMismatch, $this->licenceAmbiguous, $this->namesakeLicences,
            array_map(fn (WebsiteFact $site): WebsiteFact => $site->trusting(), $this->websites),
            $this->specialtyNames,
            $this->fzomStale,
        );
    }
}
