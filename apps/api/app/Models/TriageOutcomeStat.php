<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous weekly counts of guidance outcomes per flow. No link to a session,
 * an answer, a person or a request: only (week, flow, outcome, level, count).
 */
class TriageOutcomeStat extends Model
{
    protected $fillable = [
        'week_start',
        'flow_key',
        'outcome_id',
        'outcome_level',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'count' => 'integer',
        ];
    }

    public static function record(string $flowKey, string $outcomeId, string $level, ?Carbon $at = null): void
    {
        $week = ($at ?? Carbon::now())->copy()->startOfWeek()->toDateString();
        $now = Carbon::now();

        // One atomic upsert: concurrent completions add up instead of racing.
        DB::table('triage_outcome_stats')->upsert(
            [[
                'week_start' => $week,
                'flow_key' => $flowKey,
                'outcome_id' => $outcomeId,
                'outcome_level' => $level,
                'count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['week_start', 'flow_key', 'outcome_id'],
            ['count' => DB::raw('triage_outcome_stats.count + 1'), 'updated_at' => $now],
        );
    }
}
