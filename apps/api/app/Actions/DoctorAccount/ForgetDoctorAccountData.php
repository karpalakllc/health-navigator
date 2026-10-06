<?php

namespace App\Actions\DoctorAccount;

use App\Enums\DoctorClaimRequestStatus;
use App\Models\Doctor;
use App\Models\DoctorClaimRequest;
use App\Models\User;

/**
 * Account deletion (App\Actions\AnonymiseUser) for the doctor-account parts:
 * the profile the account managed is unlinked (withdrawing what it had
 * waiting, RemoveDoctorOwner), and its „Ова е мој профил“ requests lose their
 * free text and contact details. Decided requests stay as the record of the
 * decision; open ones are closed. Approved change requests keep their diff:
 * it describes the public profile, not the member.
 */
final class ForgetDoctorAccountData
{
    /** Staff-facing note on a claim closed because its author left. */
    private const WITHDRAWN_NOTE = 'Повлечено: сметката е избришана.';

    public function __construct(
        private readonly RemoveDoctorOwner $removeOwner,
    ) {}

    public function handle(User $user): void
    {
        Doctor::withTrashed()
            ->where('owner_user_id', $user->getKey())
            ->get()
            ->each(fn (Doctor $doctor) => $this->removeOwner->handle($doctor));

        DoctorClaimRequest::query()
            ->where('user_id', $user->getKey())
            ->pending()
            ->update([
                'status' => DoctorClaimRequestStatus::Rejected->value,
                'resolution_note' => self::WITHDRAWN_NOTE,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        DoctorClaimRequest::query()
            ->where('user_id', $user->getKey())
            ->update(['message' => '', 'contact' => '', 'updated_at' => now()]);
    }
}
