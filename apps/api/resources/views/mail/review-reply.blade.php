<x-mail::message>
# Одговор на вашата рецензија

Здраво, {{ $recipientName }},

@if ($fromDoctor)
Лекарот одговори на вашата рецензија за „**{{ $profileName }}**“. Одговорот е јавен, под вашата рецензија.
@else
Во име на „**{{ $profileName }}**“ е објавен одговор на вашата рецензија. Одговорот е јавен, под вашата рецензија.
@endif

<x-mail::panel>
{{ $replyExcerpt }}
</x-mail::panel>

Лекарот и установата го гледаат само вашето корисничко име.

<x-mail::button :url="$actionUrl">
Види го одговорот
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
