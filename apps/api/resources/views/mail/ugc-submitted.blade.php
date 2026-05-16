<x-mail::message>
# Примено

Здраво {{ $recipientName }},

Ви благодариме — вашата {{ $contentLabel }} „**{{ $contentTitle }}**“ е примена и **чека модерација**. Ќе ве известиме кога ќе биде објавена.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
