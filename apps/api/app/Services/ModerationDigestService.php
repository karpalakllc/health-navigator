<?php

namespace App\Services;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Filament\Resources\ForumPosts\ForumPostResource;
use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Support\ModerationResourceUrls;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use App\Support\ForumModerationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

final class ModerationDigestService
{
    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user): bool => $this->shouldNotify($user));
    }

    public function shouldNotify(User $user): bool
    {
        if ($user->can('reviews.view')) {
            return true;
        }

        return $user->can('forum.moderate')
            && ($user->can('forum_topics.view') || $user->can('forum_posts.view'));
    }

    /**
     * @return array{reviews: ?int, topics: ?int, posts: ?int}
     */
    public function countsFor(User $user): array
    {
        return [
            'reviews' => $user->can('reviews.view')
                ? Review::query()->where('status', ReviewStatus::Pending)->count()
                : null,
            'topics' => $user->can('forum_topics.view')
                ? ForumModerationScope::restrictTopics(
                    ForumTopic::query()->where('status', ForumContentStatus::Pending),
                    $user,
                )->count()
                : null,
            'posts' => $user->can('forum_posts.view')
                ? ForumModerationScope::restrictPosts(
                    ForumPost::query()->where('status', ForumContentStatus::Pending),
                    $user,
                )->count()
                : null,
        ];
    }

    public function totalPending(User $user): int
    {
        return array_sum(array_filter(
            $this->countsFor($user),
            fn (?int $count): bool => $count !== null,
        ));
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    public function queueLinksFor(User $user): array
    {
        $counts = $this->countsFor($user);
        $links = [];

        if ($counts['reviews'] !== null) {
            $links[] = [
                'label' => 'Рецензии',
                'count' => $counts['reviews'],
                'url' => URL::to(ModerationResourceUrls::indexPending(
                    ReviewResource::class,
                    'status',
                    ReviewStatus::Pending->value,
                )),
            ];
        }

        if ($counts['topics'] !== null) {
            $links[] = [
                'label' => 'Теми на форум',
                'count' => $counts['topics'],
                'url' => URL::to(ModerationResourceUrls::indexPending(
                    ForumTopicResource::class,
                    'status',
                    ForumContentStatus::Pending->value,
                )),
            ];
        }

        if ($counts['posts'] !== null) {
            $links[] = [
                'label' => 'Одговори на форум',
                'count' => $counts['posts'],
                'url' => URL::to(ModerationResourceUrls::indexPending(
                    ForumPostResource::class,
                    'status',
                    ForumContentStatus::Pending->value,
                )),
            ];
        }

        return $links;
    }
}
