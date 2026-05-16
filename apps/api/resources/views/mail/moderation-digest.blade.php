<x-mail::message>
# Модерација — преглед

Здраво {{ $recipientName }},

Имате **{{ $totalPending }}** ставки што чекаат модерација.

@foreach ($queues as $queue)
- **{{ $queue['label'] }}:** {{ $queue['count'] }}
@endforeach

<x-mail::button :url="$adminUrl">
Отвори админ панел
</x-mail::button>

@foreach ($queues as $queue)
@if ($queue['count'] > 0)
<x-mail::button :url="$queue['url']">
{{ $queue['label'] }} ({{ $queue['count'] }})
</x-mail::button>
@endif
@endforeach

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
