<?php

namespace App\Support\Feedback;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reads the anonymous feedback and step counters for the admin report
 * („Повратни информации“).
 */
final class FeedbackStats
{
    /**
     * Votes per item in the period, most votes first.
     *
     * @return list<array{item: string, helpful: int, not_helpful: int, total: int, helpful_pct: int|null, reasons: array<string, int>}>
     */
    public function items(CarbonInterface $from, CarbonInterface $to, string $prefix = ''): array
    {
        $votes = DB::table('feedback_counters')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($prefix !== '', fn ($query) => $query->where('item_key', 'like', $prefix.'%'))
            ->groupBy('item_key')
            ->selectRaw('item_key, SUM(helpful) AS helpful, SUM(not_helpful) AS not_helpful')
            ->get();

        $reasons = [];

        DB::table('feedback_reason_counters')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($prefix !== '', fn ($query) => $query->where('item_key', 'like', $prefix.'%'))
            ->groupBy('item_key', 'helpful', 'reason')
            ->selectRaw('item_key, helpful, reason, SUM(count) AS total')
            ->get()
            ->each(function ($row) use (&$reasons): void {
                $label = ((bool) $row->helpful ? '+' : '−').(string) $row->reason;
                $reasons[(string) $row->item_key][$label] = (int) $row->total;
            });

        $rows = [];

        foreach ($votes as $row) {
            $helpful = (int) $row->helpful;
            $notHelpful = (int) $row->not_helpful;
            $total = $helpful + $notHelpful;
            $itemReasons = $reasons[(string) $row->item_key] ?? [];
            arsort($itemReasons);

            $rows[] = [
                'item' => (string) $row->item_key,
                'helpful' => $helpful,
                'not_helpful' => $notHelpful,
                'total' => $total,
                'helpful_pct' => $total > 0 ? (int) round($helpful * 100 / $total) : null,
                'reasons' => $itemReasons,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['total'], $a['item']] <=> [$a['total'], $b['item']]);

        return $rows;
    }

    /**
     * Funnels with any step in the period, by visitors at their first step.
     *
     * @return list<string>
     */
    public function funnels(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('funnel_step_counters')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('funnel')
            ->selectRaw('funnel, SUM(reached) AS total')
            ->orderByDesc('total')
            ->orderBy('funnel')
            ->pluck('funnel')
            ->map(fn ($funnel): string => (string) $funnel)
            ->all();
    }

    /**
     * Steps of one funnel by depth: how many reached each, and the share of
     * the previous depth that got this far (drop-off = 100 − that).
     *
     * @return list<array{depth: int, step: string, reached: int, depth_total: int, of_previous_pct: int|null}>
     */
    public function funnel(string $funnel, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::table('funnel_step_counters')
            ->where('funnel', $funnel)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('depth', 'step')
            ->selectRaw('depth, step, SUM(reached) AS reached')
            ->orderBy('depth')
            ->orderByDesc('reached')
            ->orderBy('step')
            ->get();

        $byDepth = [];

        foreach ($rows as $row) {
            $byDepth[(int) $row->depth] = ($byDepth[(int) $row->depth] ?? 0) + (int) $row->reached;
        }

        $result = [];

        foreach ($rows as $row) {
            $depth = (int) $row->depth;
            $previous = $byDepth[$depth - 1] ?? null;

            $result[] = [
                'depth' => $depth,
                'step' => (string) $row->step,
                'reached' => (int) $row->reached,
                'depth_total' => $byDepth[$depth],
                'of_previous_pct' => $previous !== null && $previous > 0 ? (int) round($byDepth[$depth] * 100 / $previous) : null,
            ];
        }

        return $result;
    }
}
