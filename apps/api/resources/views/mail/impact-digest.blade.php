<x-mail::message>
# Ова се промените на кои придонесовте

Здраво, {{ $recipientName }},

Еве што се случи со вашите објави во {{ $monthLabel }}:

@if ($stats['review_views'] > 0)
- Вашите рецензии беа прикажани **{{ $stats['review_views'] }}** {{ $stats['review_views'] === 1 ? 'пат' : 'пати' }} на екранот на посетителите на профилите.
@endif
@if ($stats['helpful_votes'] > 0)
- Членовите ги означија како „Корисно“ **{{ $stats['helpful_votes'] }}** {{ $stats['helpful_votes'] === 1 ? 'пат' : 'пати' }}.
@endif
@if ($stats['replies'] > 0)
- {{ $stats['replies'] === 1 ? 'Добивте еден јавен одговор' : 'Добивте '.$stats['replies'].' јавни одговори' }} од лекар или установа.
@endif
@if ($stats['forum_answers'] > 0)
- Објавивте **{{ $stats['forum_answers'] }}** {{ $stats['forum_answers'] === 1 ? 'одговор' : 'одговори' }} во форумот.
@endif
@if ($stats['forum_replies_received'] > 0)
- Вашите теми во форумот добија **{{ $stats['forum_replies_received'] }}** {{ $stats['forum_replies_received'] === 1 ? 'нов одговор' : 'нови одговори' }}.
@endif

„Прикажана“ значи дека рецензијата била на екранот на посетител — не дека секој ја прочитал. Секој посетител се брои најмногу еднаш дневно.

<x-mail::button :url="$actionUrl">
Мои рецензии
</x-mail::button>

Поздрав,<br>
{{ config('app.name') }}

@include('mail.partials.unsubscribe')
</x-mail::message>
