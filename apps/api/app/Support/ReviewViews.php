<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\ProfileReportController;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\User;
use App\Support\Levels\LevelRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * „Прикажана N пати“ (W8-B): how many times a published review was on a
 * visitor's screen on its profile.
 *
 * The web reports the review cards that were at least half visible (an
 * IntersectionObserver, so a crawler fetching HTML, or a page nobody
 * scrolled, counts nothing; known crawlers are dropped by the web tier).
 * Each review counts at most once a day per network: the dedupe key is an
 * HMAC of the network (IPv4 address or IPv6 /64), the review and the date
 * under the app key, held in the cache until the day ends — no address,
 * cookie or device storage. The author and the doctor managing the profile
 * never count. It is a display count, not proof anyone read the review;
 * the UI says so.
 */
final class ReviewViews
{
    /** Most review ids one report may carry (a page of the list holds 15). */
    public const MAX_IDS = 30;

    /**
     * @param  list<int>  $ids
     * @return int how many reviews were counted
     */
    public static function record(array $ids, string $ip, ?User $viewer): int
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id): bool => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $reviews = Review::query()
            ->approved()
            ->onPublicProfile()
            ->whereIn('id', array_slice($ids, 0, self::MAX_IDS))
            ->with('reviewable')
            ->get(['id', 'user_id', 'reviewable_type', 'reviewable_id']);

        $network = ProfileReportController::guestNetwork($ip);
        $today = now()->toDateString();
        $ttl = (int) now()->diffInSeconds(now()->endOfDay()) + 60;
        $counted = [];

        foreach ($reviews as $review) {
            if ($viewer !== null && (int) $review->user_id === (int) $viewer->getKey()) {
                continue;
            }

            if ($viewer !== null && $review->reviewable instanceof Doctor && $review->reviewable->isOwnedBy($viewer)) {
                continue;
            }

            $key = 'review-view:'.hash_hmac('sha256', $network.'|'.$review->getKey().'|'.$today, (string) config('app.key'));

            if (Cache::add($key, true, $ttl)) {
                $counted[] = (int) $review->getKey();
            }
        }

        if ($counted === []) {
            return 0;
        }

        // The month in Macedonian time, like the digest and the levels.
        $month = now(LevelRules::TIMEZONE)->startOfMonth()->toDateString();

        DB::transaction(function () use ($counted, $month): void {
            Review::query()->whereIn('id', $counted)->toBase()->increment('view_count');

            DB::table('review_views_monthly')->upsert(
                array_map(fn (int $id): array => ['review_id' => $id, 'month' => $month, 'views' => 1], $counted),
                ['review_id', 'month'],
                ['views' => DB::raw('review_views_monthly.views + 1')],
            );
        });

        return count($counted);
    }
}
