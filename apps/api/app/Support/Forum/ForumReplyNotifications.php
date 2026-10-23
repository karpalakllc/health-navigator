<?php

namespace App\Support\Forum;

use App\Enums\ForumContentStatus;
use App\Enums\NotificationType;
use App\Mail\ForumReplyMail;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicSubscription;
use App\Models\MemberNotification;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\FrontendUrl;
use App\Support\Notifications\MemberNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * „Следи ја темата“: followers hear about each reply once it is published
 * (on either path: created approved, or approved by a moderator later).
 *
 * One unread line per topic: while a follower has not read the line, later
 * replies only raise its count, and only the first one is e-mailed — a busy
 * thread cannot flood an inbox. Opening the topic or the notifications page
 * reads it. The line names the topic, never who replied.
 */
final class ForumReplyNotifications
{
    public const REPLIES_PER_PAGE = 20;

    public static function replyPublished(ForumPost $post): void
    {
        if (! SiteSetting::current()->forumFeatureOn('forum_follow_enabled')) {
            return;
        }

        $topic = $post->topic()->with('category')->first();

        if (! $topic instanceof ForumTopic
            || $topic->status !== ForumContentStatus::Approved
            || ! $topic->category?->is_published) {
            return;
        }

        $path = self::postPath($topic, $post);
        $excerpt = self::excerpt((string) $post->body, 300);

        ForumTopicSubscription::query()
            ->where('forum_topic_id', $topic->getKey())
            ->where('user_id', '<>', $post->user_id)
            ->with('user')
            ->chunkById(200, function (Collection $subscriptions) use ($topic, $path, $excerpt): void {
                foreach ($subscriptions as $subscription) {
                    /** @var ForumTopicSubscription $subscription */
                    $user = $subscription->user;

                    if ($user instanceof User && ! $user->isAnonymised()) {
                        self::notify($user, $topic, $path, $excerpt);
                    }
                }
            });
    }

    /**
     * A post's own words for an excerpt: the lines it quotes („> …“, what
     * „Цитирај“ inserts) are left out.
     */
    public static function excerpt(string $body, int $limit): string
    {
        $own = preg_replace('/^[ \t]*>.*$/mu', '', $body) ?? $body;
        $own = trim(preg_replace("/\n{3,}/", "\n\n", $own) ?? $own);

        return Str::limit($own !== '' ? $own : trim($body), $limit);
    }

    /** The follower opened the topic: its unread reply line is read. */
    public static function markTopicRead(User $user, ForumTopic $topic): void
    {
        MemberNotification::query()
            ->where('user_id', $user->getKey())
            ->where('type', NotificationType::ForumReply->value)
            ->whereNull('read_at')
            ->where('data->topic_id', (string) $topic->getKey())
            ->update(['read_at' => now()]);
    }

    /**
     * The page of the thread a reply is on, and its anchor (the thread lists
     * published replies and removal placeholders by publication date).
     */
    public static function postPath(ForumTopic $topic, ForumPost $post): string
    {
        $before = $topic->posts()
            ->inThread()
            ->whereKeyNot($post->getKey())
            ->where(fn ($query) => $query
                ->whereRaw('coalesce(published_at, removed_at) < ?', [$post->published_at ?? now()])
                ->orWhere(fn ($same) => $same
                    ->whereRaw('coalesce(published_at, removed_at) = ?', [$post->published_at ?? now()])
                    ->where('id', '<', $post->getKey())))
            ->count();
        $page = intdiv($before, self::REPLIES_PER_PAGE) + 1;
        $base = '/forum/'.$topic->category->slug.'/'.$topic->slug;

        return $base.($page > 1 ? '?page='.$page : '').'#post-'.$post->getKey();
    }

    private static function notify(User $user, ForumTopic $topic, string $path, string $excerpt): void
    {
        /** @var MemberNotification|null $unread */
        $unread = MemberNotification::query()
            ->where('user_id', $user->getKey())
            ->where('type', NotificationType::ForumReply->value)
            ->whereNull('read_at')
            ->where('data->topic_id', (string) $topic->getKey())
            ->latest('id')
            ->first();

        if ($unread !== null) {
            $data = $unread->data;
            $data['count'] = (int) ($data['count'] ?? 1) + 1;
            $data['title'] = $topic->title;
            $unread->data = $data;
            $unread->save();

            return;
        }

        MemberNotifier::send($user, NotificationType::ForumReply, [
            // A string so the JSON lookup compares alike on PostgreSQL and SQLite.
            'topic_id' => (string) $topic->getKey(),
            'title' => $topic->title,
            // The first reply the follower has not seen yet.
            'path' => $path,
            'count' => 1,
        ], new ForumReplyMail(
            recipientName: $user->name,
            topicTitle: $topic->title,
            replyExcerpt: $excerpt,
            actionUrl: FrontendUrl::to($path),
        ));
    }
}
