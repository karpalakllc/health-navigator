<?php

namespace App\Enums;

enum DoctorChangeRequestStatus: string
{
    /** Waiting for staff; the profile still shows the old values. */
    case Pending = 'pending';

    /** Applied to the profile. */
    case Approved = 'approved';

    /** Not applied; the reason is in rejection_reason and was mailed. */
    case Rejected = 'rejected';

    /** Withdrawn by the doctor before staff decided. */
    case Withdrawn = 'withdrawn';
}
