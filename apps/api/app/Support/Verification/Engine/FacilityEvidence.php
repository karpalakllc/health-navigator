<?php

namespace App\Support\Verification\Engine;

/**
 * What the sources say about one facility (or pharmacy) profile.
 */
final readonly class FacilityEvidence
{
    /**
     * @param  list<RegisterFact>  $register  ФЗОМ register records behind the profile
     * @param  bool  $registerStale  the ФЗОМ register is older than the maximum age
     */
    public function __construct(
        public int $facilityId,
        public bool $published,
        public bool $pharmacy,
        public array $register,
        public bool $fromWebsite,
        public bool $registerStale = false,
    ) {}
}
