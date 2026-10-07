<?php

namespace App\Support\Feedback;

use Illuminate\Support\Facades\DB;

/**
 * Adds anonymous votes, reasons and step counts to the daily counters
 * (docs/urgent-care.md § Feedback). Nothing about the request survives
 * except the increment: no row per vote, no visitor, no time of day.
 */
final class FeedbackRecorder
{
    public function vote(string $item, bool $helpful): void
    {
        $this->increment('feedback_counters', [
            'day' => now()->toDateString(),
            'item_key' => $item,
            'helpful' => $helpful ? 1 : 0,
            'not_helpful' => $helpful ? 0 : 1,
        ], ['day', 'item_key'], ['helpful', 'not_helpful']);
    }

    /**
     * @param  list<string>  $reasons  already validated against the closed list
     */
    public function reasons(string $item, bool $helpful, array $reasons): void
    {
        $day = now()->toDateString();
        $reasons = array_values(array_unique($reasons));
        sort($reasons);

        DB::transaction(function () use ($day, $item, $helpful, $reasons): void {
            foreach ($reasons as $reason) {
                $this->increment('feedback_reason_counters', [
                    'day' => $day,
                    'item_key' => $item,
                    'helpful' => $helpful,
                    'reason' => $reason,
                    'count' => 1,
                ], ['day', 'item_key', 'helpful', 'reason'], ['count']);
            }
        });
    }

    public function step(string $funnel, string $step, int $depth): void
    {
        $this->increment('funnel_step_counters', [
            'day' => now()->toDateString(),
            'funnel' => $funnel,
            'step' => $step,
            'depth' => $depth,
            'reached' => 1,
        ], ['day', 'funnel', 'step', 'depth'], ['reached']);
    }

    /**
     * @param  array<string, int|string|bool>  $row
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $counters
     */
    private function increment(string $table, array $row, array $uniqueBy, array $counters): void
    {
        // `excluded.` is the proposed row in both SQLite's and PostgreSQL's
        // ON CONFLICT … DO UPDATE.
        $update = [];

        foreach ($counters as $column) {
            $update[$column] = DB::raw("{$table}.{$column} + excluded.{$column}");
        }

        DB::table($table)->upsert([$row], $uniqueBy, $update);
    }
}
