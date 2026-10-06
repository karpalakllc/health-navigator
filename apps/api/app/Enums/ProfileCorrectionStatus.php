<?php

namespace App\Enums;

enum ProfileCorrectionStatus: string
{
    /** Waiting for staff. */
    case Open = 'open';

    /**
     * Acted on: the profile was corrected, or (objection) unpublished or
     * removed. resolution_note says what was done.
     */
    case Resolved = 'resolved';

    /**
     * No change: the profile was already right, the report was not usable,
     * or (objection) the profile is kept after the balancing test.
     * resolution_note gives the reasons.
     */
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Declined => 'No change',
        };
    }
}
