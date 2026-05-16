<x-mail::message>
# Не е објавено

Здраво {{ $recipientName }},

За жал, вашата {{ $contentLabel }} „**{{ $contentTitle }}**“ **не беше објавена** по преглед од нашиот тим.

@if ($rejectionNote)
**Белешка од модераторот:** {{ $rejectionNote }}
@endif

Можете да ја проверите состојбата на вашата сметка.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
