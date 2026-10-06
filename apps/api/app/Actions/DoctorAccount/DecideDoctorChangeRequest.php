<?php

namespace App\Actions\DoctorAccount;

use App\Enums\DoctorChangeRequestStatus;
use App\Mail\DoctorChangeRequestDecidedMail;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\User;
use App\Support\DoctorAccount\DoctorProfileFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Staff approve or reject a doctor's change request (admin panel → Doctor
 * change requests). Approving writes the requested values to the profile;
 * either way the doctor is emailed the decision (with the reason on a
 * rejection). The row is re-read under a lock so two staff members cannot
 * decide it twice.
 */
final class DecideDoctorChangeRequest
{
    public function approve(DoctorChangeRequest $request, User $staff): void
    {
        $decided = DB::transaction(function () use ($request, $staff): ?DoctorChangeRequest {
            $locked = $this->lockPending($request);

            if ($locked === null) {
                return null;
            }

            /** @var Doctor $doctor */
            $doctor = Doctor::withTrashed()->whereKey($locked->doctor_id)->lockForUpdate()->firstOrFail();

            DoctorProfileFields::applySensitive($doctor, $locked->changes);

            $locked->forceFill([
                'status' => DoctorChangeRequestStatus::Approved,
                'reviewed_by_id' => $staff->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return $locked;
        });

        $this->notify($decided);
        $request->refresh();
    }

    public function reject(DoctorChangeRequest $request, User $staff, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DoctorAccountException('A reason is required to reject a change request.');
        }

        $decided = DB::transaction(function () use ($request, $staff, $reason): ?DoctorChangeRequest {
            $locked = $this->lockPending($request);

            $locked?->forceFill([
                'status' => DoctorChangeRequestStatus::Rejected,
                'reviewed_by_id' => $staff->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $locked;
        });

        $this->notify($decided);
        $request->refresh();
    }

    private function lockPending(DoctorChangeRequest $request): ?DoctorChangeRequest
    {
        /** @var DoctorChangeRequest|null $locked */
        $locked = DoctorChangeRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

        return $locked !== null && $locked->isPending() ? $locked : null;
    }

    private function notify(?DoctorChangeRequest $request): void
    {
        $recipient = $request?->user;

        if ($request === null || ! $recipient instanceof User || $recipient->isAnonymised()) {
            return;
        }

        Mail::to($recipient)->queue(new DoctorChangeRequestDecidedMail(
            recipientName: $recipient->publicName(),
            doctorName: (string) $request->doctor?->full_name,
            approved: $request->status === DoctorChangeRequestStatus::Approved,
            reason: $request->rejection_reason,
            fieldLabels: array_map(
                fn (string $field): string => (string) __('api.doctor_account.fields.'.$field, [], 'mk'),
                array_keys($request->changes),
            ),
        ));
    }
}
