<?php

namespace App\Enums;

enum BulkOperationStatus: string
{
    /** Started, not finished: a queue worker, the review page or the CLI continues it. */
    case Running = 'running';

    case Completed = 'completed';

    /** Stopped on an error; resumable from its cursor. */
    case Failed = 'failed';

    /** Stopped by staff; what was published stays published. */
    case Cancelled = 'cancelled';
}
