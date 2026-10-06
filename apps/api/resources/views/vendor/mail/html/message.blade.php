{{-- Published to translate the footer and to link the brand to the public site (APP_URL is the API); see vendor/notifications/email.blade.php. --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('zdravje.frontend_url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. Сите права задржани.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
