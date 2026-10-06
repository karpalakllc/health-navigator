@php
    // Macedonian singular for counts ending in 1, except 11 (as in the web app's tCount).
    $one = $newReports % 10 === 1 && $newReports % 100 !== 11;
@endphp
<x-mail::message>
# Нови пријави

{{ $one ? 'Пристигна' : 'Пристигнаа' }} **{{ $newReports }}** {{ $one ? 'нова пријава' : 'нови пријави' }} за содржина. Целта е секоја пријава да се прегледа во рок од 24 часа.

@foreach ($reasons as $reason)
- **{{ $reason['label'] }}:** {{ $reason['count'] }}
@endforeach

Отворени пријави вкупно: **{{ $openReports }}**.

<x-mail::button :url="$queueUrl">
Отвори ги пријавите
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
