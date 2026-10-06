<?php

namespace App\Support;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * „Корисно“ votes. Each change writes the vote row and moves
 * reviews.helpful_count in the same transaction, and only when the row really
 * changed — the unique key decides a race, so a double click or two tabs can
 * never count twice or go below zero.
 */
final class ReviewHelpfulVotes
{
    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    public static function add(Review $review, User $user): array
    {
        try {
            DB::transaction(function () use ($review, $user): void {
                DB::table('review_helpful_votes')->insert([
                    'review_id' => $review->getKey(),
                    'user_id' => $user->getKey(),
                    'created_at' => now(),
                ]);

                Review::query()->whereKey($review->getKey())->increment('helpful_count');
            });
        } catch (UniqueConstraintViolationException) {
            // Already voted: idempotent.
        }

        return self::state($review, true);
    }

    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    public static function remove(Review $review, User $user): array
    {
        DB::transaction(function () use ($review, $user): void {
            $deleted = DB::table('review_helpful_votes')
                ->where('review_id', $review->getKey())
                ->where('user_id', $user->getKey())
                ->delete();

            if ($deleted > 0) {
                Review::query()
                    ->whereKey($review->getKey())
                    ->where('helpful_count', '>', 0)
                    ->decrement('helpful_count');
            }
        });

        return self::state($review, false);
    }

    /**
     * Which of these reviews the user has marked helpful (one query per page).
     *
     * @param  list<int>  $reviewIds
     * @return array<int, bool>
     */
    public static function votedBy(User $user, array $reviewIds): array
    {
        if ($reviewIds === []) {
            return [];
        }

        return DB::table('review_helpful_votes')
            ->where('user_id', $user->getKey())
            ->whereIn('review_id', $reviewIds)
            ->pluck('review_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @return array{helpful_count: int, has_voted_helpful: bool}
     */
    private static function state(Review $review, bool $voted): array
    {
        return [
            'helpful_count' => (int) Review::query()->whereKey($review->getKey())->value('helpful_count'),
            'has_voted_helpful' => $voted,
        ];
    }
}
