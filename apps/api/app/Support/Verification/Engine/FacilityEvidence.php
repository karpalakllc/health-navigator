<?php

namespace App\Support\Verification\Engine;

/**
 * What the sources say about one facility (or pharmacy) profile.
 */
final readonly class FacilityEvidence
{
    /**
     * @param  list<RegisterFact>  $register  ФЗОМ register records behind the profile
     */
    public function __construct(
        public int $facilityId,
        public bool $published,
        public bool $pharmacy,
        public array $register,
        public bool $fromWebsite,
    ) {}
}
