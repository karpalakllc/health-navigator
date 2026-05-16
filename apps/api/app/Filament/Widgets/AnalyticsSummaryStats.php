<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsSummaryStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Activity (30 days)';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(AnalyticsService::class)->summaryForDays(30);

        return [
            Stat::make('Registrations', $summary['registrations']),
            Stat::make('Logins', $summary['logins']),
            Stat::make('Reviews submitted', $summary['reviews_submitted']),
            Stat::make('Forum topics', $summary['forum_topics']),
            Stat::make('Forum replies', $summary['forum_posts']),
            Stat::make('Search queries', $summary['search_queries']),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }
}
