<?php

namespace App\Models\Concerns;

use App\Enums\ForumContentStatus;
use App\Enums\RemovalCategory;
use App\Models\User;
use App\Support\UgcMailer;
use Illuminate\Database\Eloquent\Model;

trait ModeratesForumContent
{
    /**
     * Content can become approved on two paths: a moderator approving it later, or
     * it being created already-approved (moderation toggled off, or the author holds
     * forum.moderate). Publication bookkeeping lives here so both paths agree —
     * previously only the moderator path stamped published_at, which left
     * auto-approved topics and replies with a null publish date and broke every
     * listing that orders on it.
     */
    public static function bootModeratesForumContent(): void
    {
        static::creating(function (Model $model): void {
            if ($model->status === ForumContentStatus::Approved) {
                $model->markPublicationTimestamps();
            }
        });

        static::created(function (Model $model): void {
            if ($model->status === ForumContentStatus::Approved) {
                $model->afterApproved();
            }
        });
    }

    public function approve(User $moderator): void
    {
        $wasApproved = $this->status === ForumContentStatus::Approved;

        $this->status = ForumContentStatus::Approved;
        $this->markPublicationTimestamps();

        $this->forceFill([
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => null,
            'removed_at' => null,
            'removal_category' => null,
        ])->save();

        // Guard against double-counting if an already-approved record is re-approved.
        if (! $wasApproved) {
            $this->afterApproved();
        }

        UgcMailer::notifyApproved($this->fresh());
    }

    /**
     * Refuse pending content, or take down published content.
     *
     * Taking down published content records removed_at and a public category
     * (the transparency figures count them; a reply also keeps a placeholder
     * in its thread, see keepsPlaceWhenRemoved()). Content refused before it
     * was ever published leaves no trace.
     *
     * @param  bool  $afterReport  taken down through the report queue: the author is told it was removed
     * @param  RemovalCategory|null  $category  the public reason when published content is taken down (default „other“)
     */
    public function reject(User $moderator, ?string $note = null, bool $afterReport = false, ?RemovalCategory $category = null): void
    {
        $wasPublished = $this->status === ForumContentStatus::Approved;

        $this->forceFill([
            'status' => ForumContentStatus::Rejected,
            'published_at' => $wasPublished && $this->keepsPlaceWhenRemoved() ? $this->published_at : null,
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => $note,
            'removed_at' => $wasPublished ? now() : $this->removed_at,
            'removal_category' => $wasPublished ? ($category ?? RemovalCategory::Other) : $this->removal_category,
        ])->save();

        UgcMailer::notifyRejected($this->fresh(), removed: $afterReport);
    }

    /**
     * Stamp publication timestamps without clobbering an existing value, so
     * re-approving does not rewrite history.
     */
    protected function markPublicationTimestamps(): void
    {
        $this->published_at ??= now();
    }

    /**
     * Whether removed content keeps its publication date, so its public
     * placeholder stays in place (forum replies). Topics are not shown once
     * removed, and listings key on their published_at.
     */
    protected function keepsPlaceWhenRemoved(): bool
    {
        return false;
    }

    /**
     * Side effects of a record becoming publicly visible. Overridden where
     * publication has to update something else (see ForumPost).
     */
    protected function afterApproved(): void
    {
        //
    }
}
