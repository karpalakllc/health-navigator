<?php

namespace App\Filament\Widgets;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use App\Filament\Resources\ForumPosts\ForumPostResource;
use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingModerationOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $heading = 'Moderation queue';

    protected ?string $description = 'Pending user content awaiting staff review.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $reviewCount = Review::query()
            ->where('status', ReviewStatus::Pending)
            ->count();

        $topicCount = ForumTopic::query()
            ->where('status', ForumContentStatus::Pending)
            ->count();

        $postCount = ForumPost::query()
            ->where('status', ForumContentStatus::Pending)
            ->count();

        return [
            Stat::make('Pending reviews', $reviewCount)
                ->description('Doctor & facility reviews')
                ->color($reviewCount > 0 ? 'warning' : 'success')
                ->url(ReviewResource::getUrl('index')),
            Stat::make('Pending topics', $topicCount)
                ->description('New forum threads')
                ->color($topicCount > 0 ? 'warning' : 'success')
                ->url(ForumTopicResource::getUrl('index')),
            Stat::make('Pending replies', $postCount)
                ->description('Forum post replies')
                ->color($postCount > 0 ? 'warning' : 'success')
                ->url(ForumPostResource::getUrl('index')),
        ];
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isStaff();
    }
}
