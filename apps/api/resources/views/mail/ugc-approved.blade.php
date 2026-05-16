<x-mail::message>
# Објавено

Здраво {{ $recipientName }},

Вашата {{ $contentLabel }} „**{{ $contentTitle }}**“ е одобрена и сега е видлива на Zdravje360.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
