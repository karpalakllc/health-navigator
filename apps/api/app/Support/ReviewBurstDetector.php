<?php

namespace App\Support;

use App\Models\Review;

/**
 * A light, staff-only manipulation signal: when a profile receives
 * THRESHOLD or more reviews (any status) within WINDOW_HOURS, every review in
 * that window is stamped burst_flagged_at. The admin panel shows a badge and
 * a filter; nothing is hidden, delayed or rejected automatically.
 *
 * Checked on each new review against the 24 hours before it, so a sliding
 * window is covered: the review that completes a burst flags it, and every
 * later one inside a window that still holds THRESHOLD reviews joins it.
 */
final class ReviewBurstDetector
{
    public const THRESHOLD = 5;

    public const WINDOW_HOURS = 24;

    /**
     * Reviews started from the shared „write a review“ link
     * (via_shared_link). Lower than THRESHOLD on purpose: five reviews of any
     * kind in 24 hours already raise burst_flagged_at, so this signal only
     * adds something if it fires earlier. Three people arriving through the
     * same shared link within two days is ordinary for a well-liked doctor —
     * and exactly what a moderator should glance at before approving.
     */
    public const SHARED_LINK_THRESHOLD = 3;

    public const SHARED_LINK_WINDOW_HOURS = 48;

    public static function check(Review $review): void
    {
        $createdAt = $review->created_at ?? now();

        $window = Review::query()
            ->where('reviewable_type', $review->reviewable_type)
            ->where('reviewable_id', $review->reviewable_id)
            ->where('created_at', '>', $createdAt->copy()->subHours(self::WINDOW_HOURS))
            ->where('created_at', '<=', $createdAt);

        if ((clone $window)->count() < self::THRESHOLD) {
            return;
        }

        // A base query update: no model events (no aggregate recompute, no
        // mail) and updated_at untouched, since the review itself did not change.
        $window->whereNull('burst_flagged_at')->toBase()->update(['burst_flagged_at' => now()]);
    }

    /**
     * Same sliding window as check(), counted over shared-link reviews only;
     * stamps shared_link_burst_flagged_at on each of them. A signal for
     * extra review, never an automatic action.
     */
    public static function checkSharedLink(Review $review): void
    {
        if (! $review->via_shared_link) {
            return;
        }

        $createdAt = $review->created_at ?? now();

        $window = Review::query()
            ->where('reviewable_type', $review->reviewable_type)
            ->where('reviewable_id', $review->reviewable_id)
            ->where('via_shared_link', true)
            ->where('created_at', '>', $createdAt->copy()->subHours(self::SHARED_LINK_WINDOW_HOURS))
            ->where('created_at', '<=', $createdAt);

        if ((clone $window)->count() < self::SHARED_LINK_THRESHOLD) {
            return;
        }

        $window->whereNull('shared_link_burst_flagged_at')->toBase()->update(['shared_link_burst_flagged_at' => now()]);
    }
}
