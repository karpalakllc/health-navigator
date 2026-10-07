<?php

namespace App\Support\Verification\Engine;

/**
 * One Комора list row as evidence about one profile (komora_licences
 * staging). The licence number itself stays out: the row id identifies it.
 */
final readonly class LicenceFacts
{
    /**
     * @param  bool  $onList  on the latest complete list (not missing_since)
     * @param  bool  $valid  expiry today or later
     * @param  bool  $nameAgrees  the holder's name is the profile's name (either word order)
     * @param  bool  $fits  the licence specialty fits the profile's specialties (licence specialty mapping)
     * @param  bool  $uniqueFit  no other row on the list with this name fits the profile's specialties
     * @param  bool  $nameUnique  no other row on the list has this name at all
     * @param  bool  $general  a general doctor's licence (no specialisation)
     */
    public function __construct(
        public int $komoraLicenceId,
        public bool $onList,
        public bool $valid,
        public bool $nameAgrees,
        public bool $fits,
        public bool $uniqueFit,
        public bool $nameUnique,
        public ?string $specialty,
        public ?string $specialtyKey,
        public bool $general = false,
    ) {}

    /** Valid, on the list, the profile's name and a fitting specialty. */
    public function holds(): bool
    {
        return $this->onList && $this->valid && $this->nameAgrees && $this->fits;
    }
}
