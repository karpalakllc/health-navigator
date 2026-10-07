<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\FrontendUrl;
use App\Support\Ux\UxOverlayToken;
use App\Support\Ux\UxSamplePath;
use App\Support\Ux\UxSchema;
use App\Support\Ux\UxStats;
use App\Support\Ux\UxTargetDescriber;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * “UX analysis”: where visitors click and how far they scroll, per page
 * template and device class, from the anonymous daily counters
 * (docs/ux-heatmaps.md). The heatmap itself is drawn over the public page by a
 * staff-only overlay, opened with a short-lived link minted here.
 */
class UxAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?string $navigationLabel = 'UX analysis';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'UX analysis';

    protected static ?string $slug = 'ux-analysis';

    protected string $view = 'filament.pages.ux-analysis';

    public string $route = '/';

    /** '' = every device class. */
    public string $device = '';

    public int $days = 30;

    public string $samplePath = '/';

    public ?string $overlayUrl = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }

    public function mount(): void
    {
        $this->samplePath = UxSamplePath::for($this->route);
    }

    public function updatedRoute(): void
    {
        if (! in_array($this->route, UxSchema::routes(), true)) {
            $this->route = '/';
        }

        $this->samplePath = UxSamplePath::for($this->route);
        $this->overlayUrl = null;
    }

    public function updatedDays(): void
    {
        $this->days = max(1, min(UxOverlayToken::MAX_DAYS, $this->days));
        $this->overlayUrl = null;
    }

    /**
     * Mints a fresh overlay link for the signed-in staff member. The token goes
     * in the fragment, which browsers never send to a server.
     */
    public function createOverlayLink(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->can('analytics.view'), 403);

        $path = trim($this->samplePath);

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\s#?]/', $path)) {
            $path = UxSamplePath::for($this->route);
            $this->samplePath = $path;
        }

        $this->overlayUrl = FrontendUrl::to($path).'#ux-heatmap='.UxOverlayToken::issue($user, $this->days);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function periodOptions(): array
    {
        return [
            ['value' => 7, 'label' => 'Last 7 days'],
            ['value' => 30, 'label' => 'Last 30 days'],
            ['value' => 90, 'label' => 'Last 90 days'],
            ['value' => 180, 'label' => 'Last 180 days'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function deviceOptions(): array
    {
        return [
            '' => 'All devices',
            'mobile' => 'Mobile (< 640 px)',
            'tablet' => 'Tablet (640–1023 px)',
            'desktop' => 'Desktop (≥ 1024 px)',
        ];
    }

    /**
     * @return array<string, array{views: int, clicks: int}>
     */
    public function routeTotals(): array
    {
        [$from, $to] = $this->window();

        return app(UxStats::class)->routeTotals($this->deviceClass(), $from, $to);
    }

    /**
     * @return array{views: int, scroll: array<int, int>, tfi: array<string, int>}
     */
    public function pageSummary(): array
    {
        [$from, $to] = $this->window();

        return app(UxStats::class)->pageSummary($this->route, $this->deviceClass(), $from, $to);
    }

    /**
     * @param  'clicks'|'dead_clicks'|'rage_clicks'  $orderBy
     * @return list<array{key: string, description: string, clicks: int, dead: int, rage: int}>
     */
    public function targets(string $orderBy): array
    {
        [$from, $to] = $this->window();

        return array_map(
            fn (array $row): array => [...$row, 'description' => UxTargetDescriber::describe($row['key'])],
            app(UxStats::class)->topTargets($this->route, $this->deviceClass(), $from, $to, $orderBy),
        );
    }

    /**
     * @return array<string, string>
     */
    public function tfiLabels(): array
    {
        return [
            'tfi_under_1s' => 'under 1 s',
            'tfi_1_3s' => '1–3 s',
            'tfi_3_10s' => '3–10 s',
            'tfi_10_30s' => '10–30 s',
            'tfi_over_30s' => 'over 30 s',
            'tfi_none' => 'no click',
        ];
    }

    public function overlayTtlMinutes(): int
    {
        return max(1, (int) config('ux.overlay_ttl_minutes', 120));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        $to = Carbon::today();

        return [$to->copy()->subDays(max(1, $this->days) - 1), $to];
    }

    private function deviceClass(): ?string
    {
        return in_array($this->device, UxSchema::VIEWPORT_CLASSES, true) ? $this->device : null;
    }
}
