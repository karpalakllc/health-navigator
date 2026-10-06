<?php

namespace App\Support\Altcha;

/**
 * What AltchaGuard::verify() made of a payload. Only Verified lets the
 * request through.
 */
enum AltchaOutcome: string
{
    case Verified = 'verified';

    /** No payload at all. */
    case Missing = 'missing';

    /** Not a payload this server issued, or a wrong solution. */
    case Invalid = 'invalid';

    /** Issued here, solved, but past its expiry. */
    case Expired = 'expired';

    /** Returned sooner after issue than a person fills a form. */
    case TooFast = 'too_fast';

    /** Already used for an earlier request. */
    case Replayed = 'replayed';
}
