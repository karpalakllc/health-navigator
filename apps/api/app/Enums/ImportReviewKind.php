<?php

namespace App\Enums;

enum ImportReviewKind: string
{
    /** A draft profile the import created; publish it or leave it hidden. */
    case New = 'new';

    /** An imported value replaced the previous one on a published profile. */
    case Changed = 'changed';

    /** The source disagrees with a value someone else set; nothing was overwritten. */
    case Conflict = 'conflict';

    /** Absent from the source in consecutive runs; never deleted automatically. */
    case Missing = 'missing';

    /** The import could not place something: an ambiguous match, an unmapped specialty, a licence without a doctor. */
    case Unmatched = 'unmatched';

    /**
     * The verification engine could not decide on its own: the sources
     * nearly agree but one fact needs a person (a specialty wording pair, a
     * flagged website, a published profile that lost its verification).
     * See docs/verification.md.
     */
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
