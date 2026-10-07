<?php

namespace App\Support;

use App\Models\ForumPost;
use App\Models\User;
use App\Support\Levels\ContributorLevels;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * W8-C: „Корисно“ on forum replies, the twin of ReviewHelpfulVotes: the vote
 * row and forum_posts.helpful_count move in one transaction and only when the
 * row really changed, so a double click can never count twice.
 */
final class ForumPostHelpfulVotes
{
    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    public static function add(ForumPost $post, User $user): array
    {
        try {
            DB::transaction(function () use ($post, $user): void {
                DB::table('forum_post_helpful_votes')->insert([
                    'forum_post_id' => $post->getKey(),
                    'user_id' => $user->getKey(),
                    'created_at' => now(),
                ]);

                ForumPost::query()->whereKey($post->getKey())->increment('helpful_count');
            });
        } catch (UniqueConstraintViolationException) {
            // Already voted: idempotent.
        }

        ContributorLevels::refreshAfterCommit($post->user_id);

        return self::state($post, true);
    }

    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    public static function remove(ForumPost $post, User $user): array
    {
        DB::transaction(function () use ($post, $user): void {
            $deleted = DB::table('forum_post_helpful_votes')
                ->where('forum_post_id', $post->getKey())
                ->where('user_id', $user->getKey())
                ->delete();

            if ($deleted > 0) {
                ForumPost::query()
                    ->whereKey($post->getKey())
                    ->where('helpful_count', '>', 0)
                    ->decrement('helpful_count');
            }
        });

        ContributorLevels::refreshAfterCommit($post->user_id);

        return self::state($post, false);
    }

    /**
     * Which of these replies the user has marked helpful (one query per page).
     *
     * @param  list<int>  $postIds
     * @return array<int, bool>
     */
    public static function votedBy(User $user, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return DB::table('forum_post_helpful_votes')
            ->where('user_id', $user->getKey())
            ->whereIn('forum_post_id', $postIds)
            ->pluck('forum_post_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    private static function state(ForumPost $post, bool $voted): array
    {
        return [
            'helpful_count' => (int) ForumPost::query()->whereKey($post->getKey())->value('helpful_count'),
            'has_voted_helpful' => $voted,
        ];
    }
}
