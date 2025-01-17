<p>
Hi,
<br><br>
{{ $memberName }} just paid you {{ $amount }}.
<br><br>
Invoice Details:
</p>

<ul>
<li>Code: {{ $invoiceCode }}</li>
@if ($invoiceDescription)
<li>Description: {{ $invoiceDescription }}</li>
@endif
</ul>

<a target="_blank" href="{{ config('octiv.web_app_url') }}?search={{ $invoiceCode }}">View this invoice on Octiv.</a>
