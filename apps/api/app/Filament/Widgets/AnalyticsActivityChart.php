<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class AnalyticsActivityChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Registrations (14 days)';

    protected ?string $maxHeight = '280px';

  protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = app(AnalyticsService::class)->registrationsByDay(14);

        return [
            'datasets' => [
                [
                    'label' => 'Registrations',
                    'data' => $rows->pluck('total')->all(),
                ],
            ],
            'labels' => $rows->pluck('day')->all(),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }
}
