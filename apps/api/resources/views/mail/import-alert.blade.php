<x-mail::message>
# {{ $heading }}

**Извор:** {{ $sourceLabel }}

@if ($failed)
Увозот не заврши. Ништо не е објавено автоматски: увезените профили остануваат скриени додека тимот не ги објави.
@elseif ($stale)
Последниот успешен увоз е постар од дозволеното. Додека не се увезе повторно, овој извор не верификува ниеден профил.
@else
Последниот увоз менува необично голем дел од записите. Тоа често значи променет формат на изворот или некомплетна датотека. Прегледајте го увозот пред да објавите што било.
@endif

@if (count($rows) > 0)
@foreach ($rows as $row)
- **{{ $row['label'] }}:** {{ $row['count'] }}
@endforeach
@endif

@if ($error)
**Грешка:** {{ $error }}
@endif

@if ($reviewUrl)
<x-mail::button :url="$reviewUrl">
Отвори го прегледот
</x-mail::button>
@endif

Упатство: docs/data-import.md.

Поздрав,<br>
{{ config('app.name') }}
</x-mail::message>
