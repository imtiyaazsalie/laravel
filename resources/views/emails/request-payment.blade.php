# Hi {{ $memberName }}
You have received a new payment request from @if($username) {{ $username }},@else your facility, @endif {{ $locationName }}. Click the link below to view your invoice and authorize payment:

<a href="{{ $url }}" class="btn btn-secondary">Authorize Payment</a>
