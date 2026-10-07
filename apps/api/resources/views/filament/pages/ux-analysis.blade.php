@php
    $summary = $this->pageSummary();
    $views = $summary['views'];
    $dead = $this->targets('dead_clicks');
    $rage = $this->targets('rage_clicks');
    $top = $this->targets('clicks');
    $totals = $this->routeTotals();
    $pct = fn (int $part, int $whole): string => $whole > 0 ? number_format($part * 100 / $whole, 0).' %' : '–';
    $cell = 'padding:.5rem .75rem;border-top:1px solid rgba(127,127,127,.2);vertical-align:top;';
    $num = $cell.'text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;';
    $head = 'padding:.5rem .75rem;text-align:left;font-weight:600;font-size:.8rem;opacity:.75;';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <label style="display:flex;flex-direction:column;gap:.25rem;min-width:16rem;">
                <span style="font-size:.875rem;font-weight:500;">Page (template)</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="route" id="ux-route">
                        @foreach ($totals as $template => $row)
                            <option value="{{ $template }}">{{ $template }} — {{ $row['views'] }} views, {{ $row['clicks'] }} clicks</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Device</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="device" id="ux-device">
                        @foreach ($this->deviceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Period</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="days" id="ux-days">
                        @foreach ($this->periodOptions() as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
        <p style="margin-top:.75rem;font-size:.8rem;opacity:.7;">
            Anonymous daily totals: no visitors, sessions, IP addresses or page text.
            Profiles are grouped by template (e.g. all doctor profiles together). Kept for {{ config('ux.retention_days', 180) }} days.
        </p>
    </x-filament::section>

    <x-filament::section heading="Heatmap on the page itself" class="mt-6">
        <p style="font-size:.875rem;">
            Opens a public page with a layer that draws the clicks for your device class (the window width decides:
            mobile, tablet or desktop). The link is for you only, is valid for {{ $this->overlayTtlMinutes() }} minutes and
            shows the last {{ $days }} days. The layer stays on while you browse pages in that tab.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-top:.75rem;">
            <label style="display:flex;flex-direction:column;gap:.25rem;min-width:18rem;flex:1;">
                <span style="font-size:.875rem;font-weight:500;">Page address</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="samplePath" id="ux-sample-path" />
                </x-filament::input.wrapper>
            </label>
            <x-filament::button wire:click="createOverlayLink" icon="heroicon-o-link">
                Create heatmap link
            </x-filament::button>
        </div>
        @if ($overlayUrl)
            <div style="margin-top:.75rem;">
                <x-filament::link :href="$overlayUrl" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square">
                    Open the page with the heatmap
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));margin-top:1.5rem;">
        <x-filament::section heading="How far visitors scroll">
            <p style="font-size:.875rem;margin-bottom:.75rem;">{{ $views }} views of {{ $route }}</p>
            @foreach ($summary['scroll'] as $milestone => $count)
                <div style="display:flex;align-items:center;gap:.75rem;margin:.35rem 0;">
                    <span style="width:3.5rem;font-size:.8rem;">{{ $milestone }} %</span>
                    <div style="flex:1;height:.9rem;border-radius:.25rem;background:rgba(127,127,127,.15);overflow:hidden;">
                        <div style="height:100%;width:{{ $views > 0 ? round($count * 100 / $views, 1) : 0 }}%;background:var(--primary-500, #f59e0b);"></div>
                    </div>
                    <span style="width:6rem;text-align:right;font-size:.8rem;font-variant-numeric:tabular-nums;">{{ $pct($count, $views) }} ({{ $count }})</span>
                </div>
            @endforeach
            <p style="font-size:.75rem;opacity:.7;margin-top:.5rem;">Share of views that reached that part of the page.</p>
        </x-filament::section>

        <x-filament::section heading="Time to first click">
            @foreach ($this->tfiLabels() as $column => $label)
                @php $count = $summary['tfi'][$column] ?? 0; @endphp
                <div style="display:flex;align-items:center;gap:.75rem;margin:.35rem 0;">
                    <span style="width:5rem;font-size:.8rem;">{{ $label }}</span>
                    <div style="flex:1;height:.9rem;border-radius:.25rem;background:rgba(127,127,127,.15);overflow:hidden;">
                        <div style="height:100%;width:{{ $views > 0 ? round($count * 100 / $views, 1) : 0 }}%;background:var(--gray-400, #9ca3af);"></div>
                    </div>
                    <span style="width:6rem;text-align:right;font-size:.8rem;font-variant-numeric:tabular-nums;">{{ $pct($count, $views) }} ({{ $count }})</span>
                </div>
            @endforeach
        </x-filament::section>
    </div>

    @foreach ([
        ['heading' => 'Dead clicks — a click on something that does not respond', 'rows' => $dead, 'empty' => 'No dead clicks in this period.'],
        ['heading' => 'Rage clicks — 3 or more rapid clicks in the same spot', 'rows' => $rage, 'empty' => 'No rage clicks in this period.'],
        ['heading' => 'Most-clicked elements', 'rows' => $top, 'empty' => 'No clicks for this page yet.'],
    ] as $table)
        <x-filament::section :heading="$table['heading']" class="mt-6">
            @if ($table['rows'] === [])
                <p style="font-size:.875rem;opacity:.7;">{{ $table['empty'] }}</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr>
                                <th style="{{ $head }}">Element</th>
                                <th style="{{ $head }}">Key</th>
                                <th style="{{ $head }}text-align:right;">Clicks</th>
                                <th style="{{ $head }}text-align:right;">Dead</th>
                                <th style="{{ $head }}text-align:right;">Rage</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($table['rows'] as $row)
                                <tr>
                                    <td style="{{ $cell }}">{{ $row['description'] }}</td>
                                    <td style="{{ $cell }}"><code style="font-size:.75rem;">{{ $row['key'] }}</code></td>
                                    <td style="{{ $num }}">{{ $row['clicks'] }}</td>
                                    <td style="{{ $num }}">{{ $row['dead'] }} <span style="opacity:.6;">({{ $pct($row['dead'], $row['clicks']) }})</span></td>
                                    <td style="{{ $num }}">{{ $row['rage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
