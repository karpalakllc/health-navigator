<?php

namespace App\Support\Import\Contracts;

use Carbon\CarbonImmutable;

/**
 * One row of a licence list, as published, handed to a DoctorLicenceSink.
 */
final readonly class LicenceRecord
{
    /**
     * @param  string  $licenceNumber  as published, zero-padded (e.g. "0011032"); the stable key
     * @param  CarbonImmutable|null  $validUntil  „Датум на важност“; null when the row has none
     * @param  string  $fullName  „Име и презиме“ as published (upper case); shown to staff in the review queue only
     * @param  string|null  $specialty  „Тип на специјализација“ as published
     * @param  CarbonImmutable  $observedAt  date of the list the row came from (not the download time)
     * @param  string  $source  source key recorded in field provenance, e.g. "komora"
     * @param  string|null  $sourceReference  where in the source the row is, e.g. "Г-Ж.pdf#p12"
     * @param  int|null  $importRunId  the import_runs row of the run that produced it, when there is one
     */
    public function __construct(
        public string $licenceNumber,
        public ?CarbonImmutable $validUntil,
        public string $fullName,
        public ?string $specialty,
        public CarbonImmutable $observedAt,
        public string $source = 'komora',
        public ?string $sourceReference = null,
        public ?int $importRunId = null,
    ) {}
}
