<?php

namespace App\Enums;

/**
 * Only an approved reply is public. Staff replies are approved as they are
 * saved; a doctor's reply waits while doctor_replies_require_moderation is on.
 */
enum ReviewResponseStatus: string
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';
}
