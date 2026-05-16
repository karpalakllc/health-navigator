<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DirectoryHealthStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Directory & moderation';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $counts = app(AnalyticsService::class)->countDirectoryPublished();

        return [
            Stat::make('Published doctors', $counts['doctors']),
            Stat::make('Published facilities', $counts['facilities']),
            Stat::make('Pending reviews', $counts['reviews_pending'])
                ->color($counts['reviews_pending'] > 0 ? 'warning' : 'success'),
            Stat::make('Pending forum topics', $counts['forum_topics_pending'])
                ->color($counts['forum_topics_pending'] > 0 ? 'warning' : 'success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }
}
