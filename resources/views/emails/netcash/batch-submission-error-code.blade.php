<x-mail::message>
# Netcash Batch Submission Failed

Netcash batch submission failed with error code response.

Batch ID: {{ $debitBatch->getKey() }}

Error Code: {{ $errorCode }}

Error Message: {{ $errorMessage }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
