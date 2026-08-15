<?php

namespace App\Filament\Pages;

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

    public int $days = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function periodOptions(): array
    {
        return [
            ['value' => 7, 'label' => 'Last 7 days'],
            ['value' => 30, 'label' => 'Last 30 days'],
            ['value' => 90, 'label' => 'Last 90 days'],
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsSummaryStats::make(['days' => $this->days]),
            DirectoryHealthStats::class,
        ];
    }
}
