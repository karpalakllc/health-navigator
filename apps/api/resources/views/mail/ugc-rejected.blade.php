<x-mail::message>
@if ($removed)
# Отстрането

Здраво, {{ $recipientName }},

{{ $masculine ? 'Вашиот' : 'Вашата' }} {{ $contentLabel }} „**{{ $contentTitle }}**“ е {{ $masculine ? 'отстранет' : 'отстранета' }} од Zdravje360 по пријава и преглед од нашиот тим.

@if ($rejectionNote)
**Причина:** {{ $rejectionNote }}
@endif
@else
# Не е објавено

Здраво, {{ $recipientName }},

За жал, {{ $masculine ? 'вашиот' : 'вашата' }} {{ $contentLabel }} „**{{ $contentTitle }}**“ **не беше {{ $masculine ? 'објавен' : 'објавена' }}** по преглед од нашиот тим.

@if ($rejectionNote)
**Белешка од модераторот:** {{ $rejectionNote }}
@endif

@if ($canResubmit)
Рецензијата можете еднаш да ја измените и да ја испратите повторно од вашата сметка. Модератор повторно ќе ја прегледа; ако ни тогаш не биде објавена, одлуката е конечна.
@elseif ($finalRefusal)
Рецензијата веќе еднаш ја изменивте и ја испративте повторно, но ни тогаш не беше објавена. Оваа одлука е конечна.
@endif
@endif

Статусот на сите ваши објави можете да го видите во вашата сметка.

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
