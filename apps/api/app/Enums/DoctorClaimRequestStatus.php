<?php

namespace App\Enums;

enum DoctorClaimRequestStatus: string
{
    /** Waiting for staff to verify the person outside the platform. */
    case Pending = 'pending';

    /** The requester's account was assigned to the profile. */
    case Approved = 'approved';

    /** Not assigned; resolution_note says why (staff only). */
    case Rejected = 'rejected';
}
