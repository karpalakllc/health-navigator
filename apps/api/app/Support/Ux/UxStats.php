<?php

namespace App\Support\Ux;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the anonymous UX counters: the admin „UX анализа“ page and the
 * staff heatmap overlay. Every figure is a sum over days; there is nothing
 * finer to read.
 */
final class UxStats
{
    /** The overlay draws at most this many cells (the busiest ones). */
    public const MAX_CELLS = 6000;

    /**
     * @return list<array{x: int, y: int, clicks: int, dead: int, rage: int}>
     */
    public function heatmapCells(string $route, ?string $viewportClass, Carbon $from, Carbon $to, ?int $widthBucket = null): array
    {
        $query = $this->window(DB::table('ux_heatmap_cells'), $route, $viewportClass, $from, $to);

        if ($widthBucket !== null) {
            // The visitor's own width and its neighbours: ±80 px.
            $query->whereBetween('width_bucket', [$widthBucket - UxSchema::WIDTH_STEP, $widthBucket + UxSchema::WIDTH_STEP]);
        }

        return $query
            ->selectRaw('x_bucket, y_bucket, sum(clicks) as clicks, sum(dead_clicks) as dead, sum(rage_clicks) as rage')
            ->groupBy('x_bucket', 'y_bucket')
            ->orderByDesc('clicks')
            ->orderBy('y_bucket')
            ->orderBy('x_bucket')
            ->limit(self::MAX_CELLS)
            ->get()
            ->map(fn (object $row): array => [
                'x' => (int) $row->x_bucket,
                'y' => (int) $row->y_bucket,
                'clicks' => (int) $row->clicks,
                'dead' => (int) $row->dead,
                'rage' => (int) $row->rage,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{views: int, scroll: array<int, int>, tfi: array<string, int>}
     */
    public function pageSummary(string $route, ?string $viewportClass, Carbon $from, Carbon $to): array
    {
        $columns = ['views', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_90', 'scroll_100', ...UxSchema::TFI_COLUMNS, 'tfi_none'];
        $select = implode(', ', array_map(fn (string $column): string => "sum({$column}) as {$column}", $columns));

        $row = $this->window(DB::table('ux_page_stats'), $route, $viewportClass, $from, $to)
            ->selectRaw($select)
            ->first();

        $value = fn (string $column): int => (int) ($row->{$column} ?? 0);

        return [
            'views' => $value('views'),
            'scroll' => [
                25 => $value('scroll_25'),
                50 => $value('scroll_50'),
                75 => $value('scroll_75'),
                90 => $value('scroll_90'),
                100 => $value('scroll_100'),
            ],
            'tfi' => collect([...UxSchema::TFI_COLUMNS, 'tfi_none'])
                ->mapWithKeys(fn (string $column): array => [$column => $value($column)])
                ->all(),
        ];
    }

    /**
     * @param  'clicks'|'dead_clicks'|'rage_clicks'  $orderBy
     * @return list<array{key: string, clicks: int, dead: int, rage: int}>
     */
    public function topTargets(string $route, ?string $viewportClass, Carbon $from, Carbon $to, string $orderBy = 'clicks', int $limit = 15): array
    {
        return $this->window(DB::table('ux_element_stats'), $route, $viewportClass, $from, $to)
            ->selectRaw('target_key, sum(clicks) as clicks, sum(dead_clicks) as dead_clicks, sum(rage_clicks) as rage_clicks')
            ->groupBy('target_key')
            ->havingRaw("sum({$orderBy}) > 0")
            ->orderByDesc($orderBy)
            ->orderBy('target_key')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'key' => (string) $row->target_key,
                'clicks' => (int) $row->clicks,
                'dead' => (int) $row->dead_clicks,
                'rage' => (int) $row->rage_clicks,
            ])
            ->values()
            ->all();
    }

    /**
     * Views and clicks per page template in the window, busiest first.
     *
     * @return array<string, array{views: int, clicks: int}>
     */
    public function routeTotals(?string $viewportClass, Carbon $from, Carbon $to): array
    {
        $totals = array_fill_keys(UxSchema::routes(), ['views' => 0, 'clicks' => 0]);

        $views = DB::table('ux_page_stats')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($viewportClass, fn (Builder $query, string $class) => $query->where('viewport_class', $class))
            ->selectRaw('route, sum(views) as total')
            ->groupBy('route')
            ->pluck('total', 'route');

        $clicks = DB::table('ux_element_stats')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($viewportClass, fn (Builder $query, string $class) => $query->where('viewport_class', $class))
            ->selectRaw('route, sum(clicks) as total')
            ->groupBy('route')
            ->pluck('total', 'route');

        foreach ($totals as $route => $row) {
            $totals[$route] = [
                'views' => (int) ($views[$route] ?? 0),
                'clicks' => (int) ($clicks[$route] ?? 0),
            ];
        }

        uasort($totals, fn (array $a, array $b): int => $b['views'] <=> $a['views'] ?: $b['clicks'] <=> $a['clicks']);

        return $totals;
    }

    private function window(Builder $query, string $route, ?string $viewportClass, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->where('route', $route)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($viewportClass, fn (Builder $query, string $class) => $query->where('viewport_class', $class));
    }
}
