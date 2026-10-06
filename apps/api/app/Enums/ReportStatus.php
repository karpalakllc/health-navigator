<?php

namespace App\Enums;

enum ReportStatus: string
{
    /** Waiting in the moderation queue. */
    case Open = 'open';

    /** Reviewed; the content stays published. */
    case Kept = 'kept';

    /** Reviewed; the content was unpublished. */
    case Hidden = 'hidden';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
