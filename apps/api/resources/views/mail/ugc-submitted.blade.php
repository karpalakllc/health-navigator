<x-mail::message>
# Примено

Здраво, {{ $recipientName }},

Ви благодариме — {{ $masculine ? 'вашиот' : 'вашата' }} {{ $contentLabel }} „**{{ $contentTitle }}**“ е {{ $masculine ? 'примен' : 'примена' }} и **чека модерација**. Ќе ве известиме кога ќе биде {{ $masculine ? 'објавен' : 'објавена' }}.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
