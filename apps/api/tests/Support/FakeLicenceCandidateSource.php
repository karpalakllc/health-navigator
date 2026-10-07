<?php

namespace Tests\Support;

use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidate;
use App\Support\Licences\Contracts\LicenceCandidateSource;

/**
 * In-memory candidates: profiles registered by name, matched by NameKey like
 * the real source.
 */
final class FakeLicenceCandidateSource implements LicenceCandidateSource
{
    /** @var array<string, list<LicenceCandidate>> name key → candidates */
    private array $byName = [];

    /**
     * @param  list<string>  $specialtyNames
     */
    public function add(int $doctorId, string $fullName, array $specialtyNames = [], ?string $licenceNumber = null, bool $fallback = false): self
    {
        $this->byName[NameKey::for($fullName)][] = new LicenceCandidate(
            doctorId: $doctorId,
            licenceNumber: $licenceNumber,
            specialtyNames: $specialtyNames,
            fallback: $fallback,
        );

        return $this;
    }

    public function doctorIdForLicence(string $licenceNumber): ?int
    {
        foreach ($this->byName as $candidates) {
            foreach ($candidates as $candidate) {
                if ($candidate->licenceNumber === $licenceNumber) {
                    return $candidate->doctorId;
                }
            }
        }

        return null;
    }

    public function candidatesFor(string $fullName): array
    {
        return $this->byName[NameKey::for($fullName)] ?? [];
    }
}
