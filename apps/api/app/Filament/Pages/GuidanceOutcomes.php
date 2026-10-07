<?php

namespace App\Filament\Pages;

use App\Enums\TriageOutcomeLevel;
use App\Models\TriageOutcomeStat;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Anonymous outcome counts of symptom guidance: per flow, per week, how often
 * each level of care was the result. Built only from triage_outcome_stats —
 * no session, answer or visitor is behind these numbers.
 */
class GuidanceOutcomes extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Guidance outcomes';

    protected static string|\UnitEnum|null $navigationGroup = 'Guidance';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Guidance outcomes';

    protected static ?string $slug = 'guidance-outcomes';

    protected string $view = 'filament.pages.guidance-outcomes';

    public int $weeks = 12;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->can('triage_flows.view') || $user->can('analytics.view'));
    }

    public function updatedWeeks(): void
    {
        $this->weeks = max(1, min(104, $this->weeks));
    }

    /** @return list<string> */
    public function levels(): array
    {
        return TriageOutcomeLevel::values();
    }

    /**
     * Totals per flow and level over the period.
     *
     * @return array<string, array<string, int>> flow => level => count
     */
    public function byFlow(): array
    {
        $rows = TriageOutcomeStat::query()
            ->where('week_start', '>=', $this->from())
            ->selectRaw('flow_key, outcome_level, SUM(count) as total')
            ->groupBy('flow_key', 'outcome_level')
            ->get();

        $table = [];

        foreach ($rows as $row) {
            $table[$row->flow_key][$row->outcome_level] = (int) $row->getAttribute('total');
        }

        ksort($table);

        return $table;
    }

    /**
     * Totals per week, per level.
     *
     * @return array<string, array<string, int>> week start => level => count
     */
    public function byWeek(): array
    {
        $rows = TriageOutcomeStat::query()
            ->where('week_start', '>=', $this->from())
            ->selectRaw('week_start, outcome_level, SUM(count) as total')
            ->groupBy('week_start', 'outcome_level')
            ->orderByDesc('week_start')
            ->get();

        $table = [];

        foreach ($rows as $row) {
            $table[Carbon::parse($row->getRawOriginal('week_start'))->toDateString()][$row->outcome_level] = (int) $row->getAttribute('total');
        }

        return $table;
    }

    /**
     * How flows ended in each outcome (including red-flag stops and the
     * emergency shortcut).
     *
     * @return list<array{flow: string, outcome: string, level: string, total: int}>
     */
    public function byOutcome(): array
    {
        return TriageOutcomeStat::query()
            ->where('week_start', '>=', $this->from())
            ->selectRaw('flow_key, outcome_id, outcome_level, SUM(count) as total')
            ->groupBy('flow_key', 'outcome_id', 'outcome_level')
            ->orderBy('flow_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'flow' => (string) $row->flow_key,
                'outcome' => (string) $row->outcome_id,
                'level' => (string) $row->outcome_level,
                'total' => (int) $row->getAttribute('total'),
            ])
            ->all();
    }

    private function from(): string
    {
        return Carbon::now()->startOfWeek()->subWeeks($this->weeks - 1)->toDateString();
    }
}
