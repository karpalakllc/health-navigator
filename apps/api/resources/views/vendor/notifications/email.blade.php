{{--
    Published from the framework so the wrapper is Macedonian, like every message
    it wraps. Hard-coded rather than @lang(): mail goes out from queue workers and
    console commands, where the request locale (SetApiLocale) never applies.
--}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Настана проблем
@else
# Здраво!
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Поздрав,<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Ако копчето „{{ $actionText }}“ не работи, копирајте ја адресата подолу и залепете ја во вашиот прелистувач: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
