<x-mail::message>
# Нов одговор во темата

Здраво, {{ $recipientName }},

Во темата „**{{ $topicTitle }}**“ што ја следите е објавен нов одговор.

<x-mail::panel>
{{ $replyExcerpt }}
</x-mail::panel>

Додека не ја отворите темата, нема да ви пратиме нова порака за следните одговори во неа.

<x-mail::button :url="$actionUrl">
Прочитај го одговорот
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
