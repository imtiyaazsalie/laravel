<x-mail::message>
# Export Ready

{{ $message }}

<x-mail::button :url="$url" download="">
{{ $callToAction }}
</x-mail::button>

</x-mail::message>
