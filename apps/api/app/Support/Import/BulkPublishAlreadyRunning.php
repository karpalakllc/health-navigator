<?php

namespace App\Support\Import;

use App\Models\BulkOperation;
use RuntimeException;

/**
 * Another bulk publish is unfinished (running, or stopped and waiting to be
 * resumed or cancelled): one at a time.
 */
final class BulkPublishAlreadyRunning extends RuntimeException
{
    public function __construct(public readonly BulkOperation $operation)
    {
        parent::__construct(sprintf('Bulk publish #%d (%s) is not finished yet: %d of %d processed.', $operation->getKey(), $operation->type, $operation->processed, $operation->total));
    }
}
