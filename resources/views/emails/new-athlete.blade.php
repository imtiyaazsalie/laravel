# Hi,

A new member has registered at your facility, their details are:

- Name: {{ $user->full_name }}
- Email: {{ $user->email }}
- Package: {{ $userPackages }}
@if($paymentType)
- Payment Type: {{ $paymentType }}
@endif
@if($location)
- Location: {{ $location }}
@endif

We have emailed them with details how to log in to Octiv.
