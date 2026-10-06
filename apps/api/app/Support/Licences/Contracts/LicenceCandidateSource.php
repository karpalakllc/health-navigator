<?php

namespace App\Support\Licences\Contracts;

use App\Support\Licences\EloquentLicenceCandidateSource;
use Illuminate\Container\Attributes\Bind;

/**
 * Where the licence matcher looks for doctor profiles. The application uses
 * EloquentLicenceCandidateSource (the doctors table and W6-A's name keys);
 * tests and the local dry run substitute their own.
 */
#[Bind(EloquentLicenceCandidateSource::class)]
interface LicenceCandidateSource
{
    /** The profile that already carries this licence number, if any. */
    public function doctorIdForLicence(string $licenceNumber): ?int;

    /**
     * Imported, non-dental profiles whose normalised name equals this name's.
     *
     * @return list<LicenceCandidate>
     */
    public function candidatesFor(string $fullName): array;
}
