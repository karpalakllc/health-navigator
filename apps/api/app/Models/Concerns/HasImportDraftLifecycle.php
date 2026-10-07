<?php

namespace App\Models\Concerns;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\ImportReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Publishing a doctor or facility profile, by whatever path (the edit form's
 * toggle, a table action, the import review queue, the verification
 * engine's auto-publish), is a decision about its imported draft:
 *
 * - published_at is stamped on the first publish and kept when the profile
 *   is unpublished, so "never published" stays readable afterwards;
 * - the open "new" review items of the profile are closed („published“).
 *
 * The bulk publish actions (VerifiedDraftPublisher) only take drafts that
 * were never published, so unpublishing a profile is a decision that sticks.
 */
trait HasImportDraftLifecycle
{
    public static function bootHasImportDraftLifecycle(): void
    {
        static::saving(static function (Model $model): void {
            if ($model->isDirty('is_published') && (bool) $model->getAttribute('is_published') && $model->getAttribute('published_at') === null) {
                $model->setAttribute('published_at', now());
            }
        });

        static::saved(static function (Model $model): void {
            if (! $model->wasChanged('is_published') || ! (bool) $model->getAttribute('is_published')) {
                return;
            }

            $user = auth()->user();
            $by = $user instanceof User ? $user : null;

            ImportReviewItem::query()->open()
                ->where('kind', ImportReviewKind::New)
                ->where('subject_type', ImportReviewItem::subjectTypeOf($model))
                ->where('subject_id', $model->getKey())
                ->get()
                ->each(fn (ImportReviewItem $item) => $item->resolve(ImportReviewStatus::Resolved, 'published', $by));
        });
    }
}
