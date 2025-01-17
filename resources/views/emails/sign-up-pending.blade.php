<x-mail::message>
# Hi {{ $memberName }},

Your account has been created on Octiv for {{ $locationName }} who uses Octiv to manage their facility.

Please note that your account is in a **"pending"** state and will remain that way until payment has been made or the facility
activates it. While in a pending state, you will not have access to the Octiv application. Once your account has been activated, you will receive
an email with instructions on accessing the Octiv application.

Here is a [**link to your invoice**]({{ $paymentLink }}) for reference.

</x-mail::message>
