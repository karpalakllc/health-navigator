@php
    $levels = $this->levels();
    $byFlow = $this->byFlow();
    $byWeek = $this->byWeek();
    $byOutcome = $this->byOutcome();
    $cell = 'padding:.5rem .75rem;border-top:1px solid rgba(127,127,127,.2);';
    $num = $cell.'text-align:right;font-variant-numeric:tabular-nums;';
    $head = 'padding:.5rem .75rem;text-align:left;font-weight:600;font-size:.8rem;opacity:.75;';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <label style="display:flex;flex-direction:column;gap:.25rem;max-width:16rem;">
            <span style="font-size:.875rem;font-weight:500;">Period</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="weeks">
                    <option value="4">Last 4 weeks</option>
                    <option value="12">Last 12 weeks</option>
                    <option value="26">Last 26 weeks</option>
                    <option value="52">Last 52 weeks</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <p style="margin-top:.75rem;font-size:.8rem;opacity:.7;">
            Anonymous weekly counts only: how often each flow ended at each level of care. No session, answer, visitor or
            address is behind these numbers. „red_flag“ = stopped by the red-flag screen; „emergency_shortcut“ = the
            „Потребна ми е итна помош“ button; „_none“ = before a symptom was chosen.
        </p>
    </x-filament::section>

    <x-filament::section heading="By flow" class="mt-6">
        @if ($byFlow === [])
            <p>No completed guidance in this period.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead><tr><th style="{{ $head }}">Flow</th>@foreach ($levels as $level)<th style="{{ $head }}text-align:right;">{{ $level }}</th>@endforeach<th style="{{ $head }}text-align:right;">Total</th></tr></thead>
                    <tbody>
                        @foreach ($byFlow as $flow => $counts)
                            <tr><td style="{{ $cell }}">{{ $flow }}</td>@foreach ($levels as $level)<td style="{{ $num }}">{{ $counts[$level] ?? 0 }}</td>@endforeach<td style="{{ $num }}font-weight:600;">{{ array_sum($counts) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="By week (all flows)" class="mt-6">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead><tr><th style="{{ $head }}">Week of</th>@foreach ($levels as $level)<th style="{{ $head }}text-align:right;">{{ $level }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($byWeek as $week => $counts)
                        <tr><td style="{{ $cell }}">{{ $week }}</td>@foreach ($levels as $level)<td style="{{ $num }}">{{ $counts[$level] ?? 0 }}</td>@endforeach</tr>
                    @empty
                        <tr><td style="{{ $cell }}" colspan="{{ count($levels) + 1 }}">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="By outcome" collapsible class="mt-6">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead><tr><th style="{{ $head }}">Flow</th><th style="{{ $head }}">Outcome</th><th style="{{ $head }}">Level</th><th style="{{ $head }}text-align:right;">Count</th></tr></thead>
                <tbody>
                    @foreach ($byOutcome as $row)
                        <tr><td style="{{ $cell }}">{{ $row['flow'] }}</td><td style="{{ $cell }}">{{ $row['outcome'] }}</td><td style="{{ $cell }}">{{ $row['level'] }}</td><td style="{{ $num }}">{{ $row['total'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
