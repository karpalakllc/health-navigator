<?php

namespace App\Enums;

enum ImportRunStatus: string
{
    case Running = 'running';

    /** Parsed and applied (or, for a dry run, parsed and rolled back). */
    case Succeeded = 'succeeded';

    /** The sources answered 304: nothing changed since the last run. */
    case NotModified = 'not_modified';

    /** Stopped with nothing applied after the failing batch; `error` says why. */
    case Failed = 'failed';
}
