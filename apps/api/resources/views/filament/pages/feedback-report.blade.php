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
                <span style="font-size:.875rem;font-weight:500;">Период</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="days" id="feedback-days">
                        @foreach ($this->periodOptions() as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.875rem;font-weight:500;">Дел од страницата</span>
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
            Анонимни дневни збирови: без посетители, сесии, IP адреси или слободен текст.
            Еден посетител може да гласа повеќе пати, па бројките се ориентациски. Се чуваат {{ config('feedback.retention_days', 730) }} дена.
        </p>
    </x-filament::section>

    <x-filament::section heading="„Дали ви помогна?“ по ставка" class="mt-6">
        @if ($items === [])
            <p style="font-size:.875rem;opacity:.7;">Сè уште нема гласови во овој период.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $head }}">Ставка</th>
                            <th style="{{ $head }}text-align:right;">Помогна</th>
                            <th style="{{ $head }}text-align:right;">Не помогна</th>
                            <th style="{{ $head }}text-align:right;">Вкупно</th>
                            <th style="{{ $head }}text-align:right;">% помогна</th>
                            <th style="{{ $head }}">Причини</th>
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

    <x-filament::section heading="Докаде стигнуваат посетителите (чекори)" class="mt-6">
        @if ($funnels === [])
            <p style="font-size:.875rem;opacity:.7;">Сè уште нема забележани чекори во овој период.</p>
        @else
            <label style="display:flex;flex-direction:column;gap:.25rem;max-width:28rem;margin-bottom:1rem;">
                <span style="font-size:.875rem;font-weight:500;">Тек</span>
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
                            <th style="{{ $head }}text-align:right;">Длабочина</th>
                            <th style="{{ $head }}">Чекор</th>
                            <th style="{{ $head }}text-align:right;">Стигнале</th>
                            <th style="{{ $head }}text-align:right;">Од претходната длабочина</th>
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
            <p style="font-size:.75rem;opacity:.7;margin-top:.5rem;">„Од претходната длабочина“ = колку од посетителите на претходниот чекор стигнале до оваа длабочина; остатокот застанал.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
