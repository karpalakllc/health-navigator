<?php

namespace App\Models\Concerns;

use App\Enums\ForumContentStatus;
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
        ])->save();

        // Guard against double-counting if an already-approved record is re-approved.
        if (! $wasApproved) {
            $this->afterApproved();
        }

        UgcMailer::notifyApproved($this->fresh());
    }

    public function reject(User $moderator, ?string $note = null): void
    {
        $this->update([
            'status' => ForumContentStatus::Rejected,
            'published_at' => null,
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => $note,
        ]);

        UgcMailer::notifyRejected($this->fresh());
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
     * Side effects of a record becoming publicly visible. Overridden where
     * publication has to update something else (see ForumPost).
     */
    protected function afterApproved(): void
    {
        //
    }
}
