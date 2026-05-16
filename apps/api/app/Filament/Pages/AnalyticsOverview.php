<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalyticsActivityChart;
use App\Filament\Widgets\AnalyticsSummaryStats;
use App\Filament\Widgets\DirectoryHealthStats;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AnalyticsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Analytics';

    protected string $view = 'filament.pages.analytics-overview';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsSummaryStats::class,
            DirectoryHealthStats::class,
            AnalyticsActivityChart::class,
        ];
    }
}
