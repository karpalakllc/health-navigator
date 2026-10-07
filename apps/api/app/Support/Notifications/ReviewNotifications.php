<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Enums\ReviewResponseSource;
use App\Enums\ReviewStatus;
use App\Mail\ReviewHelpfulMail;
use App\Mail\ReviewReplyMail;
use App\Models\Review;
use App\Models\User;
use App\Support\FrontendUrl;
use Illuminate\Support\Str;

/**
 * W8-B notifications about what happens to a member's published review:
 * a public reply by the doctor or facility, and new „Корисно“ votes.
 */
final class ReviewNotifications
{
    /**
     * Called after every save of a review (Review::booted). Tells the author
     * once, when a reply first becomes public on their published review; an
     * edit of that reply does not notify again, and removing the reply
     * (Review::removeResponse) re-arms it.
     */
    public static function replyMaybePublished(Review $review): void
    {
        if (! $review->wasChanged(['response_status', 'response_body', 'status'])
            || $review->reply_notified_at !== null
            || $review->status !== ReviewStatus::Approved
            || ! $review->hasPublicResponse()) {
            return;
        }

        // Marked first, without touching updated_at or the audit log, so a
        // second save in the same request cannot send it twice.
        $marked = Review::query()
            ->whereKey($review->getKey())
            ->whereNull('reply_notified_at')
            ->toBase()
            ->update(['reply_notified_at' => now()]);

        if ($marked === 0) {
            return;
        }

        $review->reply_notified_at = now();
        $review->syncOriginalAttribute('reply_notified_at');

        $review->loadMissing(['user', 'reviewable']);
        $author = $review->user;
        $profile = ProfileRef::for($review->reviewable);

        if (! $author instanceof User || $profile === null) {
            return;
        }

        $fromDoctor = $review->response_source === ReviewResponseSource::Doctor;

        MemberNotifier::send($author, NotificationType::ReviewReply, [
            'profile' => $profile,
            'from_doctor' => $fromDoctor,
        ], new ReviewReplyMail(
            recipientName: $author->name,
            profileName: $profile['name'],
            fromDoctor: $fromDoctor,
            replyExcerpt: Str::limit((string) $review->response_body, 400),
            actionUrl: FrontendUrl::to($profile['path'].'#reviews'),
        ));
    }

    /**
     * The batched „Корисно“ notice: one per review that gained votes since
     * the author was last told, never naming who voted. A vote taken back and
     * given again is not counted twice (the high-water mark only rises).
     *
     * @return int how many authors were notified
     */
    public static function sendHelpfulBatch(): int
    {
        $sent = 0;

        Review::query()
            ->approved()
            ->whereColumn('helpful_count', '>', 'helpful_notified_count')
            ->with(['user', 'reviewable'])
            ->chunkById(200, function ($reviews) use (&$sent): void {
                foreach ($reviews as $review) {
                    /** @var Review $review */
                    $new = (int) $review->helpful_count - (int) $review->helpful_notified_count;

                    Review::query()->whereKey($review->getKey())->toBase()
                        ->update(['helpful_notified_count' => (int) $review->helpful_count]);

                    $author = $review->user;
                    $profile = ProfileRef::for($review->reviewable);

                    if (! $author instanceof User || $author->isAnonymised() || $profile === null || $new < 1) {
                        continue;
                    }

                    MemberNotifier::send($author, NotificationType::ReviewHelpful, [
                        'profile' => $profile,
                        'new_votes' => $new,
                        'total' => (int) $review->helpful_count,
                    ], new ReviewHelpfulMail(
                        recipientName: $author->name,
                        profileName: $profile['name'],
                        newVotes: $new,
                        totalVotes: (int) $review->helpful_count,
                        actionUrl: FrontendUrl::to('/account/reviews'),
                    ));

                    $sent++;
                }
            });

        return $sent;
    }
}
