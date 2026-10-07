<x-mail::message>
# Објавено

Здраво, {{ $recipientName }},

{{ $masculine ? 'Вашиот' : 'Вашата' }} {{ $contentLabel }} „**{{ $contentTitle }}**“ е {{ $masculine ? 'одобрен' : 'одобрена' }} и сега може да се види на Zdravje360.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
