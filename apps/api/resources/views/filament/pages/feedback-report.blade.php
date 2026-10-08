@php
    $items = $this->items();
    $funnels = $this->funnels();
    $steps = $this->funnelSteps();
    $labels = $this->reasonLabels();
    $cell = 'padding:.5rem .75rem;border-top:1px solid rgba(127,127,127,.2);vertical-align:top;';
    $num = $cell.'text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;';
    $head = 'padding:.5rem .75rem;text-align:left;font-weight:600;font-size:.8rem;opacity:.75;';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Period</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="days" id="feedback-days">
                        @foreach ($this->periodOptions() as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Site section</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="namespace" id="feedback-namespace">
                        @foreach ($this->namespaceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
        <p style="margin-top:.75rem;font-size:.8rem;opacity:.7;">
            Anonymous daily totals: no visitors, sessions, IP addresses or free text.
            One visitor may vote more than once, so the numbers are indicative. Kept for {{ config('feedback.retention_days', 730) }} days.
        </p>
    </x-filament::section>

    <x-filament::section heading="“Was this helpful?” by item" class="mt-6">
        @if ($items === [])
            <p style="font-size:.875rem;opacity:.7;">No votes in this period yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $head }}">Item</th>
                            <th style="{{ $head }}text-align:right;">Helpful</th>
                            <th style="{{ $head }}text-align:right;">Not helpful</th>
                            <th style="{{ $head }}text-align:right;">Total</th>
                            <th style="{{ $head }}text-align:right;">% helpful</th>
                            <th style="{{ $head }}">Reasons</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $row)
                            <tr>
                                <td style="{{ $cell }}"><code style="font-size:.75rem;">{{ $row['item'] }}</code></td>
                                <td style="{{ $num }}">{{ $row['helpful'] }}</td>
                                <td style="{{ $num }}">{{ $row['not_helpful'] }}</td>
                                <td style="{{ $num }}">{{ $row['total'] }}</td>
                                <td style="{{ $num }}">{{ $row['helpful_pct'] === null ? '–' : $row['helpful_pct'].' %' }}</td>
                                <td style="{{ $cell }}font-size:.8rem;">
                                    @forelse ($row['reasons'] as $reason => $count)
                                        <span style="white-space:nowrap;margin-right:.75rem;">{{ $labels[$reason] ?? $reason }}: {{ $count }}</span>
                                    @empty
                                        <span style="opacity:.6;">–</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="How far visitors get (steps)" class="mt-6">
        @if ($funnels === [])
            <p style="font-size:.875rem;opacity:.7;">No funnel steps recorded in this period yet.</p>
        @else
            <label style="display:flex;flex-direction:column;gap:.25rem;max-width:28rem;margin-bottom:1rem;">
                <span style="font-size:.875rem;font-weight:500;">Flow</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="funnel" id="feedback-funnel">
                        @foreach ($funnels as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $head }}text-align:right;">Depth</th>
                            <th style="{{ $head }}">Step</th>
                            <th style="{{ $head }}text-align:right;">Reached</th>
                            <th style="{{ $head }}text-align:right;">Of previous depth</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($steps as $row)
                            <tr>
                                <td style="{{ $num }}">{{ $row['depth'] }}</td>
                                <td style="{{ $cell }}"><code style="font-size:.75rem;">{{ $row['step'] }}</code></td>
                                <td style="{{ $num }}">{{ $row['reached'] }}</td>
                                <td style="{{ $num }}">{{ $row['of_previous_pct'] === null ? '–' : $row['of_previous_pct'].' %' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="font-size:.75rem;opacity:.7;margin-top:.5rem;">“Of previous depth” = how many of the visitors at the previous step reached this depth; the rest stopped.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
