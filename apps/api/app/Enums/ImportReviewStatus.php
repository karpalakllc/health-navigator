<?php

namespace App\Enums;

enum ImportReviewStatus: string
{
    case Open = 'open';

    /** Staff acted on it (published, accepted, kept, hid…); `resolution` says how. */
    case Resolved = 'resolved';

    /** Staff decided nothing needs doing. */
    case Dismissed = 'dismissed';
}
