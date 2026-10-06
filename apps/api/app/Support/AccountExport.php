<?php

namespace App\Support;

use App\Enums\ForumContentStatus;
use App\Models\AnalyticsEvent;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The member's copy of their own data (D5, right of access / portability), as
 * one JSON document written section by section so memory stays flat however
 * much someone has posted.
 *
 * Strictly the caller's own records. Other people appear only as public
 * directory names (who a review is about) and the titles of approved topics a
 * reply was posted in — never another member's name, a moderator's identity
 * or anyone's unpublished content.
 */
final class AccountExport
{
    public const FORMAT = 'zdravje360.account-export';

    public const VERSION = 1;

    private const CHUNK = 200;

    public function __construct(private readonly User $user) {}

    /**
     * Echoes the document. Called inside a StreamedResponse.
     */
    public function write(): void
    {
        echo '{';
        echo $this->member('format', self::FORMAT).',';
        echo $this->member('version', self::VERSION).',';
        echo $this->member('generated_at', now()->toIso8601String()).',';
        echo $this->member('profile', $this->profile()).',';

        $this->writeList('reviews', $this->reviews(), fn (Review $review): array => $this->review($review));
        echo ',';
        $this->writeList('forum_topics', $this->topics(), fn (ForumTopic $topic): array => $this->topic($topic));
        echo ',';
        $this->writeList('forum_posts', $this->posts(), fn (ForumPost $post): array => $this->post($post));
        echo ',';
        $this->writeList('consents', $this->topics()->whereNotNull('community_rules_accepted_at'), fn (ForumTopic $topic): array => [
            'type' => 'forum_community_rules',
            'description' => 'Прифатени правила на заедницата при отворање тема на форумот.',
            'forum_topic_id' => $topic->getKey(),
            'accepted_at' => $topic->community_rules_accepted_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeList('devices', $this->devices(), fn (PersonalAccessToken $token): array => [
            'name' => $token->name,
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ]);
        echo ',';
        $this->writeList('activity', $this->activity(), fn (AnalyticsEvent $event): array => [
            'event' => $event->event,
            'properties' => $event->properties,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ]);

        echo '}';
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        $user = $this->user;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'avatar_url' => $user->avatarUrl(),
            'roles' => $user->getRoleNames()->values()->all(),
            'moderated_forum_categories' => $user->moderatedForumCategories()->pluck('slug')->values()->all(),
        ];
    }

    /**
     * @return Builder<Review>
     */
    private function reviews(): Builder
    {
        return Review::query()->where('user_id', $this->user->getKey())->with('reviewable');
    }

    /**
     * @return array<string, mixed>
     */
    private function review(Review $review): array
    {
        $reviewable = $review->reviewable;

        return [
            'id' => $review->getKey(),
            'about' => match (true) {
                $reviewable instanceof Doctor => ['kind' => 'doctor', 'name' => $reviewable->full_name, 'slug' => $reviewable->slug],
                $reviewable instanceof Facility => [
                    'kind' => $reviewable->isPharmacy() ? 'pharmacy' : 'facility',
                    'name' => $reviewable->name,
                    'slug' => $reviewable->slug,
                ],
                default => null,
            },
            'rating' => $review->rating,
            'body' => $review->body,
            'status' => $review->status->value,
            'rejection_note' => $review->rejection_note,
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
            'published_at' => $review->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<ForumTopic>
     */
    private function topics(): Builder
    {
        return ForumTopic::query()->where('user_id', $this->user->getKey())->with('category');
    }

    /**
     * @return array<string, mixed>
     */
    private function topic(ForumTopic $topic): array
    {
        return [
            'id' => $topic->getKey(),
            'category' => $topic->category?->slug,
            'title' => $topic->title,
            'body' => $topic->body,
            'status' => $topic->status->value,
            'rejection_note' => $topic->rejection_note,
            'community_rules_accepted_at' => $topic->community_rules_accepted_at?->toIso8601String(),
            'created_at' => $topic->created_at?->toIso8601String(),
            'updated_at' => $topic->updated_at?->toIso8601String(),
            'published_at' => $topic->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<ForumPost>
     */
    private function posts(): Builder
    {
        return ForumPost::query()->where('user_id', $this->user->getKey())->with('topic');
    }

    /**
     * @return array<string, mixed>
     */
    private function post(ForumPost $post): array
    {
        $topic = $post->topic;
        // Another member's topic is named only while it is public.
        $titleVisible = $topic !== null
            && ($topic->user_id === $this->user->getKey() || $topic->status === ForumContentStatus::Approved);

        return [
            'id' => $post->getKey(),
            'forum_topic_id' => $post->forum_topic_id,
            'topic_title' => $titleVisible ? $topic->title : null,
            'body' => $post->body,
            'status' => $post->status->value,
            'rejection_note' => $post->rejection_note,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function devices(): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $this->user->getMorphClass())
            ->where('tokenable_id', $this->user->getKey());
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function activity(): Builder
    {
        return AnalyticsEvent::query()->where('user_id', $this->user->getKey());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     */
    private function writeList(string $key, Builder $query, callable $map): void
    {
        echo json_encode($key, JSON_THROW_ON_ERROR).':[';

        $first = true;

        foreach ($query->lazyById(self::CHUNK) as $model) {
            echo ($first ? '' : ',').$this->encode($map($model));
            $first = false;
        }

        echo ']';
    }

    private function member(string $key, mixed $value): string
    {
        return json_encode($key, JSON_THROW_ON_ERROR).':'.$this->encode($value);
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
