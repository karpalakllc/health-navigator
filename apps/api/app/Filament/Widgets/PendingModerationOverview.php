<?php

namespace App\Filament\Widgets;

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
use App\Services\ModerationDigestService;
use App\Support\ForumModerationScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingModerationOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $heading = 'Moderation queue';

    protected ?string $description = 'Pending user content awaiting staff review. Click a stat to open the filtered queue.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $stats = [];

        if ($user->can('reviews.view')) {
            $reviewCount = Review::query()
                ->where('status', ReviewStatus::Pending)
                ->count();

            $stats[] = Stat::make('Pending reviews', $reviewCount)
                ->description('Doctors, facilities & pharmacies')
                ->color($reviewCount > 0 ? 'warning' : 'success')
                ->url(ModerationResourceUrls::indexPending(
                    ReviewResource::class,
                    'status',
                    ReviewStatus::Pending->value,
                ));
        }

        if ($user->can('forum_topics.view')) {
            $topicCount = ForumModerationScope::restrictTopics(
                ForumTopic::query()->where('status', ForumContentStatus::Pending),
                $user,
            )->count();

            $stats[] = Stat::make('Pending topics', $topicCount)
                ->description('New forum threads')
                ->color($topicCount > 0 ? 'warning' : 'success')
                ->url(ModerationResourceUrls::indexPending(
                    ForumTopicResource::class,
                    'status',
                    ForumContentStatus::Pending->value,
                ));
        }

        if ($user->can('forum_posts.view')) {
            $postCount = ForumModerationScope::restrictPosts(
                ForumPost::query()->where('status', ForumContentStatus::Pending),
                $user,
            )->count();

            $stats[] = Stat::make('Pending replies', $postCount)
                ->description('Forum post replies')
                ->color($postCount > 0 ? 'warning' : 'success')
                ->url(ModerationResourceUrls::indexPending(
                    ForumPostResource::class,
                    'status',
                    ForumContentStatus::Pending->value,
                ));
        }

        return $stats;
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && app(ModerationDigestService::class)->shouldNotify($user);
    }
}
