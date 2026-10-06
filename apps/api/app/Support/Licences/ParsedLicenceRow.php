<?php

namespace App\Support\Licences;

use Carbon\CarbonImmutable;

/**
 * One row of a Лекарска комора licence list, as read from the PDF: the four
 * published columns plus where the row was found.
 */
final readonly class ParsedLicenceRow
{
    /**
     * @param  string  $fullName  „Име и презиме“ as published (upper case)
     * @param  string|null  $specialty  „Тип на специјализација“ as published, wrapped lines joined
     * @param  CarbonImmutable  $validUntil  „Датум на важност“
     * @param  string  $licenceNumber  „Број на лиценца“, zero-padded to 7 digits
     * @param  string  $sourceReference  e.g. "Г-Ж.pdf#p12"
     */
    public function __construct(
        public string $fullName,
        public ?string $specialty,
        public CarbonImmutable $validUntil,
        public string $licenceNumber,
        public string $sourceReference,
    ) {}
}
