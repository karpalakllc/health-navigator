<?php

namespace App\Support\Verification;

/**
 * Who made the current decision. A staff decision (Filament Verify /
 * Unverify) is kept by later automatic runs until staff release it
 * (VerificationWriter::release()).
 */
enum VerificationSource: string
{
    case Auto = 'auto';
    case Staff = 'staff';
}
