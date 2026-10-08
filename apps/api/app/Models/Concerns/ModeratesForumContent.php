<?php

namespace App\Models\Concerns;

use App\Enums\ForumContentStatus;
use App\Enums\RemovalCategory;
use App\Models\User;
use App\Support\UgcMailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        $transitioned = false;

        DB::transaction(function () use ($moderator, &$transitioned): void {
            /** @var static|null $locked */
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            // Two staff approving the same pending row must not both run
            // afterApproved() (reply counters) or both mail the author.
            if ($locked->status === ForumContentStatus::Approved) {
                $this->setRawAttributes($locked->getAttributes());
                $this->syncOriginal();

                return;
            }

            $locked->forceFill([
                'status' => ForumContentStatus::Approved,
                'published_at' => $locked->published_at ?? now(),
                'moderated_by_id' => $moderator->id,
                'moderated_at' => now(),
                'rejection_note' => null,
                'removed_at' => null,
                'removal_category' => null,
            ])->save();

            $locked->afterApproved();
            $transitioned = true;

            $this->setRawAttributes($locked->getAttributes());
            $this->syncOriginal();
        });

        // Outside the transaction: queue after_commit=true still defers the job
        // until any outer transaction commits (e.g. hideContent).
        if ($transitioned) {
            UgcMailer::notifyApproved($this->fresh());
        }
    }

    /**
     * Refuse pending content, or take down published content.
     *
     * Taking down published content records removed_at and a public category
     * (the transparency figures count them; a reply also keeps a placeholder
     * in its thread). Content refused before it
     * was ever published leaves no trace.
     *
     * @param  bool  $afterReport  taken down through the report queue: the author is told it was removed
     * @param  RemovalCategory|null  $category  the public reason when published content is taken down (default „other“)
     */
    public function reject(User $moderator, ?string $note = null, bool $afterReport = false, ?RemovalCategory $category = null): void
    {
        $transitioned = false;

        DB::transaction(function () use ($moderator, $note, $category, &$transitioned): void {
            /** @var static|null $locked */
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if ($locked->status === ForumContentStatus::Rejected) {
                $this->setRawAttributes($locked->getAttributes());
                $this->syncOriginal();

                return;
            }

            $wasPublished = $locked->status === ForumContentStatus::Approved;

            $locked->forceFill([
                'status' => ForumContentStatus::Rejected,
                // Published content keeps its publication date: a reply's
                // placeholder stays in place, and the transparency figures still
                // count the month it was published in. Public listings go by
                // status, so a removed topic is not listed for having one.
                'published_at' => $wasPublished ? $locked->published_at : null,
                'moderated_by_id' => $moderator->id,
                'moderated_at' => now(),
                'rejection_note' => $note,
                'removed_at' => $wasPublished ? now() : $locked->removed_at,
                'removal_category' => $wasPublished ? ($category ?? RemovalCategory::Other) : $locked->removal_category,
            ])->save();

            $transitioned = true;

            $this->setRawAttributes($locked->getAttributes());
            $this->syncOriginal();
        });

        if ($transitioned) {
            UgcMailer::notifyRejected($this->fresh(), removed: $afterReport);
        }
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
