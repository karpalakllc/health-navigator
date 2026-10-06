<?php

namespace App\Support\Import\Contracts;

/**
 * Where a licence matcher (the Лекарска комора import) hands its results.
 *
 * The matcher decides WHICH doctor a licence row belongs to; the sink owns
 * HOW the fact is stored: field provenance, editor locks, the rule that a
 * licence number belongs to at most one doctor, and the staff review queue.
 * A matcher never writes doctors.licence_* itself.
 *
 * Contract for implementations:
 * - attach() is idempotent: the same record twice changes nothing the second
 *   time and returns Unchanged.
 * - attach() never publishes or unpublishes a profile.
 * - A licence number already held by a different doctor is not moved: the
 *   record is queued for review and Conflict is returned.
 * - queueForReview() is idempotent per (licence number, reason): a re-run
 *   refreshes the open entry instead of adding a second one.
 * - Nothing here is shown publicly. The licence number is internal (matching
 *   and dedup); only "holds a valid licence" may reach a public profile.
 */
interface DoctorLicenceSink
{
    /**
     * Attach a licence to the doctor the matcher identified unambiguously.
     */
    public function attach(int $doctorId, LicenceRecord $licence): LicenceAttachResult;

    /**
     * Park a licence row the matcher could not attach on its own, for staff
     * to resolve in the import review queue.
     *
     * @param  list<int>  $candidateDoctorIds  doctors the row might belong to (empty when none)
     */
    public function queueForReview(LicenceRecord $licence, LicenceReviewReason $reason, array $candidateDoctorIds = []): void;
}
