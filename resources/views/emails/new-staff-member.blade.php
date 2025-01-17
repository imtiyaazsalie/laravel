# Hi,

A new staff member has been registered at your facility, their details are:

- Name: {{ $user->full_name }}
- Email: {{ $user->email }}
- User Type: {{ $userType }}
@if($location)
- Location: {{ $location }}
@endif

