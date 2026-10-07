@php
    $list = 'list-style:disc;padding-left:1.25rem;display:grid;gap:.25rem;';
@endphp
<x-filament::section class="mt-6" :heading="'Outcome: '.($outcome['title'] ?? '?')" :description="($outcome['level'] ?? '').(($outcome['crisis'] ?? false) ? ' (crisis)' : '').' — '.$ref">
    @if ($outcome)
        <div style="display:grid;gap:.75rem;">
            <p>{{ $outcome['summary'] }}</p>
            @if (! empty($outcome['call']))
                <p><strong>Call:</strong> @foreach ($outcome['call'] as $line) <a href="tel:{{ $line['number'] }}" style="text-decoration:underline;">{{ $line['number'] }}</a> {{ $line['label'] ?? '' }}@if (! $loop->last), @endif @endforeach</p>
            @endif
            <div><strong>Зошто</strong><ul style="{{ $list }}">@foreach ($outcome['reasons'] ?? [] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
            <div><strong>Што да направите сега</strong><ul style="{{ $list }}">@foreach ($outcome['do_now'] ?? [] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
            @if (! empty($outcome['watch_for']))
                <div><strong>Внимавајте на</strong><ul style="{{ $list }}">@foreach ($outcome['watch_for'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
            @endif
            <p style="font-size:.85rem;opacity:.75;">Care: {{ $outcome['care']['setting'] ?? '' }}{{ ! empty($outcome['care']['specialties']) ? ' — '.implode(', ', $outcome['care']['specialties']) : '' }}{{ ! empty($outcome['care']['facility_types']) ? ' — '.implode(', ', $outcome['care']['facility_types']) : '' }}</p>
        </div>
    @else
        <p>Unknown outcome.</p>
    @endif
</x-filament::section>
