<x-mail::message>
# Вашата рецензија помага

Здраво, {{ $recipientName }},

@if ($newVotes === 1)
Еден член ја означи вашата рецензија за „**{{ $profileName }}**“ како „Корисно“.
@else
{{ $newVotes }} членови ја означија вашата рецензија за „**{{ $profileName }}**“ како „Корисно“.
@endif
@if ($totalVotes > $newVotes)
Вкупно: {{ $totalVotes }}.
@endif

Ви благодариме што го споделивте искуството — тоа им помага на другите полесно да изберат.

<x-mail::button :url="$actionUrl">
Мои рецензии
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
