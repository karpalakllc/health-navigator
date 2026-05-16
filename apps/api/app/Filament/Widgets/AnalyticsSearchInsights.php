<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\Widget;

class AnalyticsSearchInsights extends Widget
{
    public int $days = 30;

    protected string $view = 'filament.widgets.analytics-search-insights';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'days' => $this->days,
            'queries' => app(AnalyticsService::class)->topSearchQueries($this->days, 10),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }
}
