<?php

namespace App\Actions\DoctorAccount;

use App\Enums\DoctorChangeRequestStatus;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Unlink the managing account from a doctor profile: staff „Remove account“,
 * and account deletion (AnonymiseUser). What the account still had waiting is
 * withdrawn with it — pending change requests, and doctor replies not yet
 * approved — so nothing it wrote can be published after it lost the profile.
 * Approved replies stay; they were reviewed and are the doctor's public word.
 */
final class RemoveDoctorOwner
{
    public function handle(Doctor $doctor): void
    {
        DB::transaction(function () use ($doctor): void {
            /** @var Doctor $locked */
            $locked = Doctor::withTrashed()->whereKey($doctor->getKey())->lockForUpdate()->firstOrFail();
            $ownerId = $locked->owner_user_id;

            if ($ownerId === null) {
                return;
            }

            $locked->forceFill([
                'owner_user_id' => null,
                'owner_linked_at' => null,
                'owner_linked_by_id' => null,
            ])->save();

            DoctorChangeRequest::query()
                ->where('doctor_id', $locked->getKey())
                ->pending()
                ->get()
                ->each(fn (DoctorChangeRequest $request) => $request->forceFill([
                    'status' => DoctorChangeRequestStatus::Withdrawn,
                ])->save());

            $locked->reviews()
                ->withPendingDoctorReply()
                ->where('response_by_id', $ownerId)
                ->get()
                ->each(fn (Review $review) => $review->removeResponse());
        });

        $doctor->refresh();
    }
}
