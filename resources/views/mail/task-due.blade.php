<x-mail::message>
# Follow-up {{ $type }} due

{{ $leadName }} has a follow-up {{ $type }} due {{ $dueAt }}.

@if (filled($notes))
{{ $notes }}
@endif

<x-mail::button :url="$url">
View task
</x-mail::button>

Thanks,<br>
{{ config('app.company_name') ?: config('app.name') }}
</x-mail::message>
