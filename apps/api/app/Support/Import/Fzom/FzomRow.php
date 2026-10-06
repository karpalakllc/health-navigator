<?php

namespace App\Support\Import\Fzom;

/**
 * One <Lekar> contract row of the ФЗОМ „Шифрарник на лекари“, reduced to the
 * fields we may keep. FzomXmlReader never copies the excluded ones into this
 * object: the team nurse (ClenNaTim), absence reasons and validity statuses
 * (PricinaOtsustvo, *StatusValidnostID — possibly health data), and every
 * substitution (RedovnaZamena, VZamena*).
 *
 * `facsimile` is an internal key: it is stored on the doctor to match the
 * next import and is never displayed or exported publicly.
 */
final readonly class FzomRow
{
    public function __construct(
        public string $file,
        public int $contractTypeId,
        public string $contractType,
        public ?string $facilityCode,
        public ?string $taxNumber,
        public string $facilityName,
        public ?string $workUnit,
        public ?string $activity,
        public ?string $specialties,
        public ?string $address,
        public ?string $town,
        public string $facsimile,
        public string $firstName,
        public string $lastName,
        public ?string $validFrom,
        public ?string $validTo,
    ) {}
}
