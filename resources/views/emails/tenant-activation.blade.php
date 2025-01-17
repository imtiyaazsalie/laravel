Hi {{ $user->full_name }},

Your facility, {{ $tenant->name }}, has been activated on Octiv.
You may use the details below to access your account:

*   URL: [https://app.octivfitness.com](https://app.octivfitness.com)
*   Email: {{ $user->email }}
*   Password: {{ $password }}
