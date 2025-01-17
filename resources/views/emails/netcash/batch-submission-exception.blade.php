<x-mail::message>
# Netcash Batch Submission Failed

Netcash batch submission failed with exception.

Batch ID: {{ $debitBatch->getKey() }}

Exception: {{ $exception }}

{{ $exceptionMessage }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
