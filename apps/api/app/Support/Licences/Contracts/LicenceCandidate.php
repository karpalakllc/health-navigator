<?php

namespace App\Support\Licences\Contracts;

/**
 * A doctor profile a licence row might belong to: same normalised name. The
 * matcher decides with the specialties.
 */
final readonly class LicenceCandidate
{
    /**
     * @param  string|null  $licenceNumber  the licence already on the profile, if any
     * @param  list<int>  $specialtyIds  our specialties of the profile
     * @param  list<string>  $specialtyNames  their names (often the ФЗОМ wording for imported profiles)
     * @param  list<string>  $specialtySlugs  their slugs
     * @param  bool  $fallback  a website-only draft (no ФЗОМ record): considered only when no
     *                          ФЗОМ profile carries the name (KomoraLicenceMatcher)
     */
    public function __construct(
        public int $doctorId,
        public ?string $licenceNumber = null,
        public array $specialtyIds = [],
        public array $specialtyNames = [],
        public array $specialtySlugs = [],
        public bool $fallback = false,
    ) {}
}
