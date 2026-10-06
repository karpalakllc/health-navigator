<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\AnalyticsActivityChart;
use App\Filament\Widgets\AnalyticsCommunityChart;
use App\Filament\Widgets\AnalyticsSummaryStats;
use App\Filament\Widgets\DirectoryHealthStats;
use App\Filament\Widgets\PendingModerationOverview;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Filament's stats and chart widgets poll every 5 seconds unless told otherwise,
 * which re-ran every dashboard aggregate per open admin tab.
 */
class DashboardWidgetPollingTest extends TestCase
{
    /** @return array<string, array{class-string, ?string}> */
    public static function widgets(): array
    {
        return [
            'registrations chart' => [AnalyticsActivityChart::class, null],
            'forum activity chart' => [AnalyticsCommunityChart::class, null],
            'activity summary' => [AnalyticsSummaryStats::class, null],
            'directory health' => [DirectoryHealthStats::class, null],
            'moderation queue' => [PendingModerationOverview::class, '60s'],
        ];
    }

    /** @param  class-string  $widget */
    #[DataProvider('widgets')]
    public function test_dashboard_widgets_do_not_poll_every_few_seconds(string $widget, ?string $expected): void
    {
        $interval = (new ReflectionMethod($widget, 'getPollingInterval'))->invoke(new $widget);

        $this->assertSame($expected, $interval);
    }
}
