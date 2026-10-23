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
     * @param  string|null  $listDate  date of the Комора list the row was last on (Y-m-d)
     * @param  string|null  $graceUntil  expired, but after $listDate and no newer list imported since:
     *                                   the last day the grace covers (Y-m-d); null otherwise
     * @param  string|null  $validUntil  the expiry as on the list (Y-m-d; internal, never public)
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
        public ?string $listDate = null,
        public ?string $graceUntil = null,
        public ?string $validUntil = null,
    ) {}

    /**
     * Expired after the date of the list it came from — the list could not
     * show a renewal yet — within the grace period, and no newer list was
     * imported since (import.verification.licence_grace_days).
     */
    public function inGrace(): bool
    {
        return ! $this->valid && $this->onList && $this->graceUntil !== null;
    }

    /**
     * Like holds(), but a licence in its grace period counts. Only the rules
     * backed by a current ФЗОМ contract use it.
     */
    public function holdsWithGrace(): bool
    {
        return $this->onList && ($this->valid || $this->inGrace()) && $this->nameAgrees && $this->fits;
    }

    /**
     * Internal evidence of the grace (admin text: LicenceGrace::adminNote()).
     *
     * @return array{expired_on: string|null, list_date: string|null, grace_until: string|null}
     */
    public function graceEvidence(): array
    {
        return ['expired_on' => $this->validUntil, 'list_date' => $this->listDate, 'grace_until' => $this->graceUntil];
    }

    /** Valid, on the list, the profile's name and a fitting specialty. */
    public function holds(): bool
    {
        return $this->onList && $this->valid && $this->nameAgrees && $this->fits;
    }
}
