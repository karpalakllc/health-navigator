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
                <span style="font-size:.875rem;font-weight:500;">Страница (шаблон)</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="route" id="ux-route">
                        @foreach ($totals as $template => $row)
                            <option value="{{ $template }}">{{ $template }} — {{ $row['views'] }} посети, {{ $row['clicks'] }} кликови</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Уред</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="device" id="ux-device">
                        @foreach ($this->deviceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Период</span>
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
            Анонимни дневни збирови: без посетители, сесии, IP адреси или текст од страницата.
            Профилите се збирни по шаблон (на пр. сите профили на лекари заедно). Се чуваат {{ config('ux.retention_days', 180) }} дена.
        </p>
    </x-filament::section>

    <x-filament::section heading="Топлинска мапа на самата страница" class="mt-6">
        <p style="font-size:.875rem;">
            Отвора јавна страница со слој што ги црта кликовите за вашиот тип уред (ширината на прозорецот одлучува:
            мобилен, таблет или десктоп). Линкот е само за вас, важи {{ $this->overlayTtlMinutes() }} минути и
            ги покажува последните {{ $days }} дена. Слојот останува вклучен додека шетате низ страниците во тоа јазиче.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-top:.75rem;">
            <label style="display:flex;flex-direction:column;gap:.25rem;min-width:18rem;flex:1;">
                <span style="font-size:.875rem;font-weight:500;">Адреса на страницата</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="samplePath" id="ux-sample-path" />
                </x-filament::input.wrapper>
            </label>
            <x-filament::button wire:click="createOverlayLink" icon="heroicon-o-link">
                Создај линк за топлинска мапа
            </x-filament::button>
        </div>
        @if ($overlayUrl)
            <div style="margin-top:.75rem;">
                <x-filament::link :href="$overlayUrl" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square">
                    Отвори ја страницата со топлинска мапа
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));margin-top:1.5rem;">
        <x-filament::section heading="Колку далеку се лизга">
            <p style="font-size:.875rem;margin-bottom:.75rem;">{{ $views }} посети на {{ $route }}</p>
            @foreach ($summary['scroll'] as $milestone => $count)
                <div style="display:flex;align-items:center;gap:.75rem;margin:.35rem 0;">
                    <span style="width:3.5rem;font-size:.8rem;">{{ $milestone }} %</span>
                    <div style="flex:1;height:.9rem;border-radius:.25rem;background:rgba(127,127,127,.15);overflow:hidden;">
                        <div style="height:100%;width:{{ $views > 0 ? round($count * 100 / $views, 1) : 0 }}%;background:var(--primary-500, #f59e0b);"></div>
                    </div>
                    <span style="width:6rem;text-align:right;font-size:.8rem;font-variant-numeric:tabular-nums;">{{ $pct($count, $views) }} ({{ $count }})</span>
                </div>
            @endforeach
            <p style="font-size:.75rem;opacity:.7;margin-top:.5rem;">Удел од посетите што стигнале до тој дел од страницата.</p>
        </x-filament::section>

        <x-filament::section heading="Време до првиот клик">
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
        ['heading' => 'Мртви кликови — клик на нешто што не реагира', 'rows' => $dead, 'empty' => 'Нема мртви кликови во овој период.'],
        ['heading' => 'Бесни кликови — 3 или повеќе брзи кликови на исто место', 'rows' => $rage, 'empty' => 'Нема бесни кликови во овој период.'],
        ['heading' => 'Најкликани елементи', 'rows' => $top, 'empty' => 'Сè уште нема кликови за оваа страница.'],
    ] as $table)
        <x-filament::section :heading="$table['heading']" class="mt-6">
            @if ($table['rows'] === [])
                <p style="font-size:.875rem;opacity:.7;">{{ $table['empty'] }}</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr>
                                <th style="{{ $head }}">Елемент</th>
                                <th style="{{ $head }}">Клуч</th>
                                <th style="{{ $head }}text-align:right;">Кликови</th>
                                <th style="{{ $head }}text-align:right;">Мртви</th>
                                <th style="{{ $head }}text-align:right;">Бесни</th>
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
