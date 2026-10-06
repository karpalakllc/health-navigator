<?php

namespace App\Support\Verification;

/** What a VerificationWriter call did. */
enum VerificationResult: string
{
    /** Became verified, or stayed verified on a different basis. */
    case Verified = 'verified';

    /** Was verified, is not any more. */
    case Unverified = 'unverified';

    /** Same status (and basis) as before; evidence/reasons and checked_at refreshed. */
    case Unchanged = 'unchanged';

    /** Automatic call on a profile with a staff decision: nothing written. */
    case StaffDecisionKept = 'staff_decision_kept';
}
