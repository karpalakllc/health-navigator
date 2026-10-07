@if (! empty($unsubscribeUrl))
<x-mail::subcopy>
@switch($unsubscribeType)
@case('review_reminder')
Овој потсетник го побаравте на профилот. [Откажи ги сите потсетници]({{ $unsubscribeUrl }}) · [Поставки за известувања]({{ $preferencesUrl }})
@break
@case('impact_digest')
Месечниот преглед го вклучивте во вашата сметка. [Исклучи го месечниот преглед]({{ $unsubscribeUrl }}) · [Поставки за известувања]({{ $preferencesUrl }})
@break
@default
Не сакате вакви пораки? [Исклучи ги со еден клик]({{ $unsubscribeUrl }}) · [Поставки за известувања]({{ $preferencesUrl }})
@endswitch
</x-mail::subcopy>
@endif
