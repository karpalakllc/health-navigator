<?php

namespace App\Support\Verification;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the verification columns on doctors and facilities
 * (incl. pharmacies). Called by the import engine (automatic, $staff = null)
 * and by the Filament Verify / Unverify / Release actions (staff).
 *
 * Rules:
 * - A staff decision (verification_source = staff) is never overridden by an
 *   automatic call: it returns StaffDecisionKept and writes nothing. Staff
 *   hand the profile back to the engine with release().
 * - Status changes (verified ↔ unverified, or a new basis) save the model
 *   normally (updated_at, search index) and are written to the audit log
 *   (log „verification“). Re-confirming the same status only refreshes the
 *   internal reasons and verification_checked_at, quietly: a nightly run
 *   must not touch updated_at (sitemap lastmod) or flood the log.
 * - Evidence and reasons are internal (staff/engine only); the API exposes
 *   status + public basis label (HasVerification::publicVerification()).
 *   Never put a licence number or the ФЗО facsimile in $evidence.
 */
final class VerificationWriter
{
    private const COLUMNS = ['verified_at', 'verification_basis', 'verification_reasons', 'verification_source', 'verified_by_id', 'verification_checked_at'];

    /**
     * Mark the profile verified on $basis.
     *
     * @param  list<array<string, scalar|null>|string>  $evidence  internal, JSON-serialisable (e.g. ['source' => 'fzom', 'facility_id' => 12])
     * @param  User|null  $staff  null = automatic (the engine); a user = staff decision (sticky)
     * @param  string|null  $note  staff reason (required by the Filament action), stored internally and in the audit log
     */
    public function verify(Doctor|Facility $subject, VerificationBasis $basis, array $evidence = [], ?User $staff = null, ?string $note = null): VerificationResult
    {
        return DB::transaction(fn (): VerificationResult => $this->verifyLocked($subject, $basis, $evidence, $staff, $note));
    }

    /**
     * @param  list<array<string, scalar|null>|string>  $evidence
     */
    private function verifyLocked(Doctor|Facility $subject, VerificationBasis $basis, array $evidence, ?User $staff, ?string $note): VerificationResult
    {
        if ($staff === null && $this->staffDecidedMeanwhile($subject)) {
            return VerificationResult::StaffDecisionKept;
        }

        $changed = ! $subject->isVerified() || $subject->verification_basis !== $basis;
        $now = now();

        $subject->forceFill([
            'verified_at' => $changed ? $now : $subject->verified_at,
            'verification_basis' => $basis,
            'verification_reasons' => array_filter([
                'status' => VerificationStatus::Verified->value,
                'evidence' => $evidence,
                'note' => $note,
            ], fn ($value) => $value !== null),
            'verification_source' => $staff !== null ? VerificationSource::Staff : VerificationSource::Auto,
            'verified_by_id' => $staff?->getKey(),
            'verification_checked_at' => $now,
        ]);

        $this->persist($subject, $changed || $staff !== null);

        if ($changed || $staff !== null) {
            $this->log($subject, 'verified', $staff, [
                'basis' => $basis->value,
                'note' => $note,
            ]);
        }

        return $changed ? VerificationResult::Verified : VerificationResult::Unchanged;
    }

    /**
     * Mark the profile unverified (or keep it so) with a reason.
     *
     * @param  string  $reason  automatic: a stable snake_case code (e.g. 'licence_expired', 'source_removed',
     *                          'no_licence', 'ambiguous_name'); staff: free text (required by the Filament action)
     * @param  list<array<string, scalar|null>|string>  $evidence  internal, JSON-serialisable
     * @param  User|null  $staff  null = automatic (the engine); a user = staff decision (sticky)
     */
    public function unverify(Doctor|Facility $subject, string $reason, ?User $staff = null, array $evidence = []): VerificationResult
    {
        return DB::transaction(fn (): VerificationResult => $this->unverifyLocked($subject, $reason, $staff, $evidence));
    }

    /**
     * @param  list<array<string, scalar|null>|string>  $evidence
     */
    private function unverifyLocked(Doctor|Facility $subject, string $reason, ?User $staff, array $evidence): VerificationResult
    {
        if ($staff === null && $this->staffDecidedMeanwhile($subject)) {
            return VerificationResult::StaffDecisionKept;
        }

        $changed = $subject->isVerified();
        $previousBasis = $subject->verification_basis;
        // A profile that lost an automatic verification keeps saying so on
        // later automatic runs (the engine's verification_lost item stays
        // open) until it is verified again or staff decide.
        $lostBasis = $changed ? $previousBasis?->value
            : ($staff === null && $subject->verification_source === VerificationSource::Auto ? ($subject->verification_reasons['previous_basis'] ?? null) : null);

        $subject->forceFill([
            'verified_at' => null,
            'verification_basis' => null,
            'verification_reasons' => array_filter([
                'status' => VerificationStatus::Unverified->value,
                'reason' => $reason,
                'evidence' => $evidence,
                'previous_basis' => $lostBasis,
            ], fn ($value) => $value !== null),
            'verification_source' => $staff !== null ? VerificationSource::Staff : VerificationSource::Auto,
            'verified_by_id' => $staff?->getKey(),
            'verification_checked_at' => now(),
        ]);

        $this->persist($subject, $changed || $staff !== null);

        if ($changed || $staff !== null) {
            $this->log($subject, 'unverified', $staff, [
                'previous_basis' => $previousBasis?->value,
                'reason' => $reason,
            ]);
        }

        return $changed ? VerificationResult::Unverified : VerificationResult::Unchanged;
    }

    /**
     * Hand a staff decision back to the engine: the current status stays
     * until the next automatic run re-evaluates the profile.
     */
    public function release(Doctor|Facility $subject, User $staff): void
    {
        if (! $subject->hasStaffVerificationDecision()) {
            return;
        }

        $subject->forceFill(['verification_source' => VerificationSource::Auto]);
        $this->persist($subject, false);
        $this->log($subject, 'released', $staff, []);
    }

    /**
     * An automatic call works on a copy loaded earlier (the engine's chunk):
     * re-read the verification columns with the row locked until the write
     * commits, so a staff decision made meanwhile always wins and the
     * status is decided from what is stored now.
     */
    private function staffDecidedMeanwhile(Doctor|Facility $subject): bool
    {
        $row = $subject->newQueryWithoutScopes()
            ->whereKey($subject->getKey())
            ->lockForUpdate()
            ->toBase()
            ->first(self::COLUMNS);

        if ($row !== null) {
            $subject->setRawAttributes(array_merge($subject->getAttributes(), (array) $row));
            $subject->syncOriginalAttributes(self::COLUMNS);
        }

        return $subject->hasStaffVerificationDecision();
    }

    /**
     * $visible: a public change (status/basis) or a staff decision → a normal
     * save (events: search index, caches). Otherwise a quiet write that
     * leaves updated_at alone.
     */
    private function persist(Doctor|Facility $subject, bool $visible): void
    {
        DB::transaction(function () use ($subject, $visible): void {
            if ($visible) {
                $subject->save();

                return;
            }

            $subject->withoutTimestamps(fn () => $subject->saveQuietly());
        });
    }

    /**
     * @param  array<string, string|null>  $properties
     */
    private function log(Doctor|Facility $subject, string $event, ?User $staff, array $properties): void
    {
        $entry = activity('verification')
            ->performedOn($subject)
            ->event($event)
            ->withProperties(array_filter(
                $properties + ['source' => $staff !== null ? VerificationSource::Staff->value : VerificationSource::Auto->value],
                fn ($value) => $value !== null,
            ));

        if ($staff !== null) {
            $entry->causedBy($staff);
        }

        $entry->log('verification_'.$event);
    }
}
