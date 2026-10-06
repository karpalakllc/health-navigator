<?php

namespace Tests\Support;

use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceAttachResult;
use App\Support\Import\Contracts\LicenceRecord;
use App\Support\Import\Contracts\LicenceReviewReason;

/**
 * In-memory DoctorLicenceSink following the contract: idempotent attach,
 * a number held by another doctor is a Conflict (and queued), one review
 * item per licence number (a re-run refreshes it, the reason may change).
 */
final class FakeDoctorLicenceSink implements DoctorLicenceSink
{
    /** @var array<string, int> licence number → doctor id */
    public array $holders = [];

    /** @var array<int, LicenceRecord> doctor id → last record attached */
    public array $attached = [];

    /** @var array<string, array{record: LicenceRecord, reason: LicenceReviewReason, candidates: list<int>}> */
    public array $review = [];

    /** @var list<int> doctors whose licence fields staff locked */
    public array $locked = [];

    public function attach(int $doctorId, LicenceRecord $licence): LicenceAttachResult
    {
        if (in_array($doctorId, $this->locked, true)) {
            return LicenceAttachResult::Locked;
        }

        $holder = $this->holders[$licence->licenceNumber] ?? null;

        if ($holder !== null && $holder !== $doctorId) {
            $this->queueForReview($licence, LicenceReviewReason::Ambiguous, [$doctorId, $holder]);

            return LicenceAttachResult::Conflict;
        }

        if (($this->attached[$doctorId] ?? null) == $licence) {
            return LicenceAttachResult::Unchanged;
        }

        $this->holders[$licence->licenceNumber] = $doctorId;
        $this->attached[$doctorId] = $licence;

        return LicenceAttachResult::Attached;
    }

    public function queueForReview(LicenceRecord $licence, LicenceReviewReason $reason, array $candidateDoctorIds = []): void
    {
        $this->review[$licence->licenceNumber] = [
            'record' => $licence,
            'reason' => $reason,
            'candidates' => $candidateDoctorIds,
        ];
    }

    /**
     * @return list<string> licence numbers queued with this reason
     */
    public function queued(LicenceReviewReason $reason): array
    {
        $numbers = [];

        foreach ($this->review as $item) {
            if ($item['reason'] === $reason) {
                $numbers[] = $item['record']->licenceNumber;
            }
        }

        sort($numbers);

        return $numbers;
    }
}
