<x-mail::message>
# Не е објавено

Здраво, {{ $recipientName }},

За жал, {{ $masculine ? 'вашиот' : 'вашата' }} {{ $contentLabel }} „**{{ $contentTitle }}**“ **не беше {{ $masculine ? 'објавен' : 'објавена' }}** по преглед од нашиот тим.

@if ($rejectionNote)
**Белешка од модераторот:** {{ $rejectionNote }}
@endif

Статусот на сите ваши објави можете да го видите во вашата сметка.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
