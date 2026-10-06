<?php

namespace App\Support\Verification;

/** The public status in the API (`verification.status`). */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case Unverified = 'unverified';
}
