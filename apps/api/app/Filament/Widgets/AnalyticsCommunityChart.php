<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class AnalyticsCommunityChart extends ChartWidget
{
    public int $days = 30;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Forum activity';

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
        $analytics = app(AnalyticsService::class);
        $topics = $analytics->eventCountByDay('forum.topic_created', $this->days);
        $posts = $analytics->eventCountByDay('forum.post_created', $this->days);

        $labels = $topics->pluck('day')
            ->merge($posts->pluck('day'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $topicByDay = $topics->pluck('total', 'day');
        $postByDay = $posts->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label' => 'New topics',
                    'data' => array_map(
                        fn (string $day): int => (int) ($topicByDay[$day] ?? 0),
                        $labels,
                    ),
                ],
                [
                    'label' => 'New replies',
                    'data' => array_map(
                        fn (string $day): int => (int) ($postByDay[$day] ?? 0),
                        $labels,
                    ),
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getHeading(): ?string
    {
        return "Forum activity ({$this->days} days)";
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }
}
