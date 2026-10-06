<?php

namespace App\Support\Import\Contracts;

enum LicenceReviewReason: string
{
    /** Several doctors fit the row (same normalised name and compatible specialty). */
    case Ambiguous = 'ambiguous';

    /** No doctor fits; a licence holder we have no workplace for. */
    case NoMatch = 'no_match';

    /** A doctor fits by name but the specialty does not map to theirs. */
    case SpecialtyMismatch = 'specialty_mismatch';
}
