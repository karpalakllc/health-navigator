<?php

namespace App\Actions\DoctorAccount;

use App\Enums\DoctorClaimRequestStatus;
use App\Models\Doctor;
use App\Models\DoctorClaimRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Staff link a member account to a doctor profile, after verifying the person
 * outside the platform. The account can then edit the profile's practice
 * details, request changes to the sensitive ones and reply to its reviews
 * („Мој профил“). One account per profile and one profile per account.
 *
 * Authorisation (doctors.assign_owner) is the caller's job; the audit log
 * records the change on the doctor row with the staff member as causer.
 */
final class AssignDoctorOwner
{
    public function handle(Doctor $doctor, User $account, User $staff, ?DoctorClaimRequest $claim = null): void
    {
        if (! $account->isClient() || $account->isAnonymised()) {
            throw new DoctorAccountException('Only an active member account can manage a doctor profile.');
        }

        if ($account->isSuspended()) {
            throw new DoctorAccountException('This account is suspended. Lift the suspension first.');
        }

        try {
            DB::transaction(function () use ($doctor, $account, $staff, $claim): void {
                /** @var Doctor $locked */
                $locked = Doctor::withTrashed()->whereKey($doctor->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->owner_user_id !== null && (int) $locked->owner_user_id !== (int) $account->getKey()) {
                    throw new DoctorAccountException('This profile is already managed by another account. Remove that account first.');
                }

                $other = Doctor::withTrashed()
                    ->where('owner_user_id', $account->getKey())
                    ->whereKeyNot($locked->getKey())
                    ->exists();

                if ($other) {
                    throw new DoctorAccountException('This account already manages another doctor profile.');
                }

                if ((int) $locked->owner_user_id !== (int) $account->getKey()) {
                    $locked->forceFill([
                        'owner_user_id' => $account->getKey(),
                        'owner_linked_at' => now(),
                        'owner_linked_by_id' => $staff->getKey(),
                    ])->save();
                }

                if ($claim !== null && $claim->status === DoctorClaimRequestStatus::Pending) {
                    $claim->forceFill([
                        'status' => DoctorClaimRequestStatus::Approved,
                        'resolved_by_id' => $staff->getKey(),
                        'resolved_at' => now(),
                    ])->save();
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Two staff members assigning at once: the unique index decided.
            throw new DoctorAccountException('This account already manages another doctor profile.');
        }

        $doctor->refresh();
    }
}
