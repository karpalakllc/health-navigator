<?php

namespace App\Observers;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Support\Levels\ContributorLevels;
use Illuminate\Database\Eloquent\Model;

/**
 * W8-C: a review, topic or reply being approved, refused, taken down or
 * deleted changes its author's points, so their levels are recomputed once
 * the change is committed. „Корисно“ votes are written without models and
 * refresh the author themselves (ReviewHelpfulVotes, ForumPostHelpfulVotes).
 */
class ContributorLevelObserver
{
    public function saved(Review|ForumTopic|ForumPost $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged(['status', 'removed_at', 'published_at'])) {
            return;
        }

        $this->refresh($model);
    }

    public function deleted(Review|ForumTopic|ForumPost $model): void
    {
        $this->refresh($model);
    }

    private function refresh(Model $model): void
    {
        ContributorLevels::refreshAfterCommit($model->getAttribute('user_id'));

        // Replies only count inside a published topic, so a topic's own
        // status moves the points of everyone who answered in it.
        if ($model instanceof ForumTopic) {
            $model->posts()->distinct()->pluck('user_id')
                ->each(fn ($userId) => ContributorLevels::refreshAfterCommit($userId));
        }
    }
}
