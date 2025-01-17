<x-mail::message>
# Hi coach,

@if($isRequestDemo)
A new lead has requested a demo using the widget.
@else
A new lead has signed up using the widget.
@endif


@component('mail::panel')
@lang('Name'): {{ $member->name }}

@lang('Email'): {{ $member->email_address }}

@if ($member->date_of_birth)
@lang('DOB'): {{ $member->date_of_birth }}
@endif

@if ($member->mobile_number)
@lang('Phone'): {{ $member->mobile_number }}
@endif

@lang('Location'): {{ $locationName }}

@if ($member->notes)
@lang('Notes'): {{ $member->notes }}
@endif


@endcomponent

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
