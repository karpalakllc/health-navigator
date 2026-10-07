<?php

namespace App\Support\Ux;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Folds one validated tracker batch into the daily counters.
 *
 * Nothing about the batch survives except the increments: no row per click or
 * per view, no time finer than the day, and no visitor, session or address.
 * Each table gets one upsert, so concurrent batches for the same cell all count.
 */
final class UxEventRecorder
{
    private const UPSERT_CHUNK = 200;

    /**
     * @param  list<array{r: string, vc: string, wb: int, x: int, y: ?int, k: string, d: bool, g: bool}>  $clicks
     * @param  list<array{r: string, vc: string, s: int, t: ?int}>  $views
     */
    public function record(array $clicks, array $views, ?Carbon $day = null): void
    {
        $date = ($day ?? Carbon::today())->toDateString();

        $cells = [];
        $elements = [];

        foreach ($clicks as $click) {
            $dead = $click['d'] ? 1 : 0;
            $rage = $click['g'] ? 1 : 0;

            // Clicks on fixed or sticky elements (header, tab bar) have no stable
            // document position; they count for their target only.
            if ($click['y'] !== null) {
                $cellKey = implode('|', [$click['r'], $click['vc'], $click['wb'], $click['x'], $click['y']]);
                $cells[$cellKey] ??= [
                    'day' => $date,
                    'route' => $click['r'],
                    'viewport_class' => $click['vc'],
                    'width_bucket' => $click['wb'],
                    'x_bucket' => $click['x'],
                    'y_bucket' => $click['y'],
                    'clicks' => 0,
                    'dead_clicks' => 0,
                    'rage_clicks' => 0,
                ];
                $cells[$cellKey]['clicks']++;
                $cells[$cellKey]['dead_clicks'] += $dead;
                $cells[$cellKey]['rage_clicks'] += $rage;
            }

            $elementKey = implode('|', [$click['r'], $click['vc'], $click['k']]);
            $elements[$elementKey] ??= [
                'day' => $date,
                'route' => $click['r'],
                'viewport_class' => $click['vc'],
                'target_key' => $click['k'],
                'clicks' => 0,
                'dead_clicks' => 0,
                'rage_clicks' => 0,
            ];
            $elements[$elementKey]['clicks']++;
            $elements[$elementKey]['dead_clicks'] += $dead;
            $elements[$elementKey]['rage_clicks'] += $rage;
        }

        $pages = [];

        foreach ($views as $view) {
            $pageKey = $view['r'].'|'.$view['vc'];
            $pages[$pageKey] ??= [
                'day' => $date,
                'route' => $view['r'],
                'viewport_class' => $view['vc'],
                'views' => 0,
                'scroll_25' => 0,
                'scroll_50' => 0,
                'scroll_75' => 0,
                'scroll_90' => 0,
                'scroll_100' => 0,
                'tfi_under_1s' => 0,
                'tfi_1_3s' => 0,
                'tfi_3_10s' => 0,
                'tfi_10_30s' => 0,
                'tfi_over_30s' => 0,
                'tfi_none' => 0,
            ];
            $pages[$pageKey]['views']++;

            // Cumulative: a view that reached 75 % also reached 25 and 50, so the
            // funnel reads straight off the columns.
            foreach ([25, 50, 75, 90, 100] as $milestone) {
                if ($view['s'] >= $milestone) {
                    $pages[$pageKey]['scroll_'.$milestone]++;
                }
            }

            $pages[$pageKey][$view['t'] === null ? 'tfi_none' : UxSchema::TFI_COLUMNS[$view['t']]]++;
        }

        $this->increment('ux_heatmap_cells', $cells,
            ['day', 'route', 'viewport_class', 'width_bucket', 'x_bucket', 'y_bucket'],
            ['clicks', 'dead_clicks', 'rage_clicks']);

        $this->increment('ux_element_stats', $elements,
            ['day', 'route', 'viewport_class', 'target_key'],
            ['clicks', 'dead_clicks', 'rage_clicks']);

        $this->increment('ux_page_stats', $pages,
            ['day', 'route', 'viewport_class'],
            ['views', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_90', 'scroll_100',
                'tfi_under_1s', 'tfi_1_3s', 'tfi_3_10s', 'tfi_10_30s', 'tfi_over_30s', 'tfi_none']);
    }

    /**
     * @param  array<string, array<string, int|string>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $counters
     */
    private function increment(string $table, array $rows, array $uniqueBy, array $counters): void
    {
        if ($rows === []) {
            return;
        }

        // `excluded.` is the proposed row in both SQLite's and PostgreSQL's
        // ON CONFLICT … DO UPDATE.
        $update = [];
        foreach ($counters as $column) {
            $update[$column] = DB::raw("{$table}.{$column} + excluded.{$column}");
        }

        foreach (array_chunk(array_values($rows), self::UPSERT_CHUNK) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }
}
