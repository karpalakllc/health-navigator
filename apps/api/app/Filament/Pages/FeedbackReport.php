<?php

namespace App\Filament\Pages;

use App\Support\Feedback\FeedbackStats;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * „Повратни информации“: the anonymous „Дали ви помогна?“ votes per guide,
 * urgent-care page or guidance outcome, and how far visitors get through
 * multi-step flows (docs/urgent-care.md § Feedback).
 */
class FeedbackReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    protected static ?string $navigationLabel = 'Повратни информации';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Повратни информации';

    protected static ?string $slug = 'feedback-report';

    protected string $view = 'filament.pages.feedback-report';

    public int $days = 30;

    /** '' = every item; otherwise a namespace such as `guide:`. */
    public string $namespace = '';

    public string $funnel = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }

    public function updatedDays(): void
    {
        $this->days = max(1, min(730, $this->days));
    }

    public function updatedNamespace(): void
    {
        if (! array_key_exists($this->namespace, $this->namespaceOptions())) {
            $this->namespace = '';
        }
    }

    /**
     * @return array<string, string>
     */
    public function namespaceOptions(): array
    {
        return [
            '' => 'Сите',
            'guide:' => 'Водичи',
            'urgent-care:' => 'Каде веднаш',
            'guidance:' => 'Насоки за симптоми',
            'page:' => 'Други страници',
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function periodOptions(): array
    {
        return [
            ['value' => 7, 'label' => 'Последни 7 дена'],
            ['value' => 30, 'label' => 'Последни 30 дена'],
            ['value' => 90, 'label' => 'Последни 90 дена'],
            ['value' => 365, 'label' => 'Последна година'],
        ];
    }

    /**
     * @return list<array{item: string, helpful: int, not_helpful: int, total: int, helpful_pct: int|null, reasons: array<string, int>}>
     */
    public function items(): array
    {
        [$from, $to] = $this->window();

        return app(FeedbackStats::class)->items($from, $to, $this->namespace);
    }

    /**
     * @return list<string>
     */
    public function funnels(): array
    {
        [$from, $to] = $this->window();

        return app(FeedbackStats::class)->funnels($from, $to);
    }

    /**
     * @return list<array{depth: int, step: string, reached: int, depth_total: int, of_previous_pct: int|null}>
     */
    public function funnelSteps(): array
    {
        $funnels = $this->funnels();
        $funnel = in_array($this->funnel, $funnels, true) ? $this->funnel : ($funnels[0] ?? null);

        if ($funnel === null) {
            return [];
        }

        $this->funnel = $funnel;
        [$from, $to] = $this->window();

        return app(FeedbackStats::class)->funnel($funnel, $from, $to);
    }

    /**
     * The reason codes in words (the chips the visitor saw).
     *
     * @return array<string, string>
     */
    public function reasonLabels(): array
    {
        return [
            '+clear' => 'Јасно објаснето',
            '+found-place' => 'Најдов каде да одам',
            '+next-step' => 'Знам што следно',
            '−unclear' => 'Нејасно',
            '−not-found' => 'Не најдов што барав',
            '−wrong-info' => 'Погрешни податоци',
            '−outdated' => 'Застарено',
            '−not-relevant' => 'Не се однесува на мене',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        $to = Carbon::today();

        return [$to->copy()->subDays(max(1, $this->days) - 1), $to];
    }
}
