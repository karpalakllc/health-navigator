<?php

namespace App\Models\Concerns;

use App\Models\Doctor;
use App\Models\User;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationSource;
use App\Support\Verification\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read side of „Верификуван“ / „Неверификуван“ on doctors and facilities
 * (incl. pharmacies). The columns are written only by
 * App\Support\Verification\VerificationWriter.
 *
 * @property Carbon|null $verified_at
 * @property VerificationBasis|null $verification_basis
 * @property array<string, mixed>|null $verification_reasons
 * @property VerificationSource|null $verification_source
 * @property int|null $verified_by_id
 * @property Carbon|null $verification_checked_at
 */
trait HasVerification
{
    public function initializeHasVerification(): void
    {
        $this->mergeCasts([
            'verified_at' => 'datetime',
            'verification_basis' => VerificationBasis::class,
            'verification_reasons' => 'array',
            'verification_source' => VerificationSource::class,
            'verification_checked_at' => 'datetime',
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verificationStatus(): VerificationStatus
    {
        return $this->isVerified() ? VerificationStatus::Verified : VerificationStatus::Unverified;
    }

    public function hasStaffVerificationDecision(): bool
    {
        return $this->verification_source === VerificationSource::Staff;
    }

    /**
     * „Само верификувани“.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('verified_at'));
    }

    /**
     * The public `verification` object in API resources: status and the
     * public basis label only, never the internal evidence.
     *
     * `licence_list_date` (Y-m-d): the date of the Лекарска комора list a
     * doctor's verification rests on („Според листата на Лекарска комора од
     * 02.07.2026.“), null for every other basis. Never the licence's expiry.
     *
     * @return array{status: string, basis: string|null, basis_label: string|null, licence_list_date: string|null}
     */
    public function publicVerification(): array
    {
        $basis = $this->isVerified() ? $this->verification_basis : null;

        return [
            'status' => $this->verificationStatus()->value,
            'basis' => $basis?->value,
            'basis_label' => $basis?->publicLabel($this->verificationLabelSubject()),
            'licence_list_date' => $basis !== null ? $this->verificationListDate() : null,
        ];
    }

    /**
     * The list date the engine recorded for a licence rule (fzom_licence,
     * website_licence); only that date is read from the evidence.
     */
    private function verificationListDate(): ?string
    {
        if (! $this instanceof Doctor || $this->hasStaffVerificationDecision()) {
            return null;
        }

        $evidence = $this->verification_reasons['evidence'][0] ?? null;

        if (! is_array($evidence) || ! in_array($evidence['rule'] ?? null, ['fzom_licence', 'website_licence'], true)) {
            return null;
        }

        $date = $evidence['list_date'] ?? null;

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    /**
     * Whose wording the public basis label takes. A dentist verified by the
     * ФЗОМ contract alone (engine rule `fzom_dentist`) is „Регистар на ФЗОМ“
     * — no Комора licence is involved. Only the rule name is read from the
     * internal evidence; nothing of it is exposed.
     *
     * @return 'doctor'|'dentist'|'facility'
     */
    private function verificationLabelSubject(): string
    {
        if (! $this instanceof Doctor) {
            return 'facility';
        }

        $rule = $this->verification_reasons['evidence'][0]['rule'] ?? null;

        return $rule === 'fzom_dentist' ? 'dentist' : 'doctor';
    }
}
