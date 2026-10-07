<?php

namespace App\Listeners;

use App\Events\ImportRunFinished;
use App\Support\Verification\Engine\VerificationEngine;
use Throwable;

/**
 * Re-evaluates every profile's verification after a successful ФЗОМ or
 * Комора apply (by hand or scheduled): a licence that expired or left the
 * list, or a doctor gone from ФЗОМ, loses its verification the same day.
 * The website import runs the engine itself (it is not announced).
 * Skipped while another engine run is in progress; the nightly run catches
 * up. config import.verification.after_import turns it off.
 *
 * Registered by listener discovery (app/Listeners); do not also
 * Event::listen() it.
 */
class VerifyAfterImportRun
{
    public function __construct(private readonly VerificationEngine $engine) {}

    public function handle(ImportRunFinished $event): void
    {
        if (! $event->succeeded || ! (bool) config('import.verification.after_import')) {
            return;
        }

        // The import itself succeeded: a verification failure is reported,
        // never turned into a failed import (the nightly run retries).
        try {
            $this->engine->runQuietly();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
