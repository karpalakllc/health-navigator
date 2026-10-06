<?php

namespace App\Support\Import;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceAttachResult;
use App\Support\Import\Contracts\LicenceRecord;
use App\Support\Import\Contracts\LicenceReviewReason;
use Illuminate\Support\Facades\DB;

/**
 * Stores licence facts from a licence matcher (Лекарска комора) on doctors.
 *
 * The licence is one lockable field, "licence" (number, expiry and specialty
 * as published together). A number never moves between doctors silently,
 * and a doctor who already holds a DIFFERENT number is not overwritten:
 * both go to the review queue. The number stays internal; the public
 * profile only learns "holds a valid licence" (Doctor::hasValidLicence()).
 */
final class EloquentDoctorLicenceSink implements DoctorLicenceSink
{
    public const FIELD = 'licence';

    public function attach(int $doctorId, LicenceRecord $licence): LicenceAttachResult
    {
        return DB::transaction(function () use ($doctorId, $licence): LicenceAttachResult {
            $doctor = Doctor::query()->lockForUpdate()->find($doctorId);

            if ($doctor === null) {
                return LicenceAttachResult::DoctorNotFound;
            }

            $provenance = FieldProvenance::query()
                ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
                ->where('subject_id', $doctor->getKey())
                ->where('field', self::FIELD)
                ->first();

            if ($provenance?->locked) {
                return LicenceAttachResult::Locked;
            }

            $holder = Doctor::withTrashed()
                ->where('licence_number', $licence->licenceNumber)
                ->whereKeyNot($doctor->getKey())
                ->value('id');

            if ($holder !== null || ($doctor->licence_number !== null && $doctor->licence_number !== $licence->licenceNumber)) {
                $this->raise($licence, 'conflict', 'Licence '.$licence->licenceNumber.' conflicts with an existing licence', [
                    'reason' => 'conflict',
                    'doctor_id' => $doctor->getKey(),
                    'current_holder_id' => $holder !== null ? (int) $holder : null,
                    'doctor_has_other_number' => $doctor->licence_number !== null && $doctor->licence_number !== $licence->licenceNumber,
                ], ImportReviewKind::Conflict, $doctor);

                return LicenceAttachResult::Conflict;
            }

            $value = implode('|', [$licence->licenceNumber, $licence->validUntil?->toDateString() ?? '', $licence->specialty ?? '']);
            $unchanged = $doctor->licence_number === $licence->licenceNumber
                && $doctor->licence_valid_until?->toDateString() === $licence->validUntil?->toDateString()
                && $doctor->licence_specialty_raw === $licence->specialty;

            $doctor->forceFill([
                'licence_number' => $licence->licenceNumber,
                'licence_valid_until' => $licence->validUntil?->toDateString(),
                'licence_specialty_raw' => $licence->specialty !== null ? mb_substr($licence->specialty, 0, 255) : null,
                'licence_source' => $licence->source,
                'licence_checked_at' => $licence->observedAt,
            ]);

            // Only the check date moved: no activity-log entry for that.
            $unchanged ? $doctor->saveQuietly() : $doctor->save();

            ($provenance ?? new FieldProvenance([
                'subject_type' => FieldProvenance::SUBJECT_DOCTOR,
                'subject_id' => $doctor->getKey(),
                'field' => self::FIELD,
            ]))->fill([
                'source' => $licence->source,
                'value' => $value,
                'observed_at' => $licence->observedAt,
            ])->save();

            ImportReviewItem::autoResolve($licence->source, ImportReviewKind::Unmatched, $this->key($licence, null), 'attached');

            return $unchanged ? LicenceAttachResult::Unchanged : LicenceAttachResult::Attached;
        });
    }

    public function queueForReview(LicenceRecord $licence, LicenceReviewReason $reason, array $candidateDoctorIds = []): void
    {
        $this->raise($licence, $reason->value, sprintf('Licence %s (%s): %s', $licence->licenceNumber, $licence->fullName, str_replace('_', ' ', $reason->value)), [
            'reason' => $reason->value,
            'candidate_doctor_ids' => array_values(array_map('intval', $candidateDoctorIds)),
        ], ImportReviewKind::Unmatched);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function raise(LicenceRecord $licence, string $reason, string $title, array $details, ImportReviewKind $kind, ?Doctor $subject = null): void
    {
        ImportReviewItem::raise(
            $licence->source,
            $kind,
            $kind === ImportReviewKind::Unmatched ? $this->key($licence, null) : $this->key($licence, $reason),
            $title,
            $details + [
                'licence_number' => $licence->licenceNumber,
                'full_name' => $licence->fullName,
                'specialty' => $licence->specialty,
                'valid_until' => $licence->validUntil?->toDateString(),
                'source_reference' => $licence->sourceReference,
            ],
            $subject,
            $licence->importRunId,
        );
    }

    /**
     * One open "unmatched" item per licence number whatever the reason (a
     * re-run may change ambiguous → no match); conflicts are keyed apart.
     */
    private function key(LicenceRecord $licence, ?string $reason): string
    {
        return 'licence:'.$licence->licenceNumber.($reason !== null ? ':'.$reason : '');
    }
}
