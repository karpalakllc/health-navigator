<?php

namespace App\Support\Licences;

use App\Support\Import\Contracts\LicenceReviewReason;

/**
 * What the matcher decided for one licence row: attach it to one profile, or
 * park it for review with the profiles it might belong to.
 */
final readonly class LicenceDecision
{
    /**
     * @param  list<int>  $candidateDoctorIds
     */
    private function __construct(
        public ParsedLicenceRow $row,
        public ?int $doctorId,
        public ?LicenceReviewReason $reason,
        public array $candidateDoctorIds,
        public bool $alreadyAttached,
    ) {}

    public static function attach(ParsedLicenceRow $row, int $doctorId, bool $alreadyAttached = false): self
    {
        return new self($row, $doctorId, null, [$doctorId], $alreadyAttached);
    }

    /**
     * @param  list<int>  $candidateDoctorIds
     */
    public static function review(ParsedLicenceRow $row, LicenceReviewReason $reason, array $candidateDoctorIds = []): self
    {
        return new self($row, null, $reason, array_values(array_unique($candidateDoctorIds)), false);
    }

    public function attaches(): bool
    {
        return $this->doctorId !== null;
    }
}
