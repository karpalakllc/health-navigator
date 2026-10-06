<?php

namespace App\Support\Import;

use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\SourceRecord;

/**
 * The import rows about one profile (source records, field provenance,
 * review items) are polymorphic, so no foreign key removes them: when a
 * doctor or facility is deleted for good, they go here. A doctor's
 * suppression (ImportSuppression) was recorded before and stays.
 */
final class ImportBookkeeping
{
    public static function forget(string $subjectType, int $subjectId): void
    {
        FieldProvenance::query()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->delete();
        ImportReviewItem::query()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->delete();
        SourceRecord::query()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->delete();
    }
}
