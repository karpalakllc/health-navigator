@php
    // Macedonian singular for counts ending in 1, except 11 (as in the web app's tCount).
    $one = $newRequests % 10 === 1 && $newRequests % 100 !== 11;
@endphp
<x-mail::message>
# Нови барања за профили

{{ $one ? 'Пристигна' : 'Пристигнаа' }} **{{ $newRequests }}** {{ $one ? 'ново барање' : 'нови барања' }} за профили во именикот.

@foreach ($types as $type)
- **{{ $type['label'] }}:** {{ $type['count'] }} (рок за одговор: {{ $type['days'] }} дена)
@endforeach

Отворени барања вкупно: **{{ $openRequests }}**.
@if ($overdueRequests > 0)
Со поминат рок: **{{ $overdueRequests }}**.
@endif
@if ($priorityProfiles > 0)
Профили со повеќе пријави од различни лица (се прикажуваат први): **{{ $priorityProfiles }}**.
@endif

<x-mail::button :url="$queueUrl">
Отвори ги барањата
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
