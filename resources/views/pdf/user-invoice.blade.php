<x-layouts.pdf>

    <x-slot:title>
        @if ($invoice->type === \App\Enums\InvoiceType::INVOICE) Invoice @else Credit Note @endif for {{ $invoice->created_on->format('d M y') }}
    </x-slot:title>


    <div class="invoice-container">
        <table cellpadding="0" cellspacing="0">
            <tbody>
            <tr>
                <td>
                    <table>
                        <tr>
                            @if ($invoice->invoice_location?->logofile)
                                <td class="align-center-small">
                                    <img src="{{ $image }}" class="studio-logo">
                                </td>
                            @endif
                            <td class="align-center-small">
                                Invoice #: {{ $invoice->code }}<br>
                                Created: {{ $invoice->created_on->format('d M y') }}<br>
                                Due: {{ $invoice->due_on->format('d M y') }}<br>
                                Member: {{ $invoice->invoice_member_name }}<br>
                                Social Security Number: {{ $invoice->invoice_member_social_security_number }}<br>
                                Address: {{ $invoice->invoice_member_address }}<br>
                                Email: {{ $invoice->invoice_email }}<br />
                                @if($invoice->userTenant)
                                    Member id: {{ $invoice->userTenant->member_id }}<br>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td>
                    <table>
                        <tr>
                            <td class="align-center-small" style="text-align: left !important;">
                                <strong>{{ $location?->name }}</strong>
                                <br>
                                @if($location?->vat) Vat number: {{ $location?->vat }} @endif<br>
                                {!! nl2br(e($location->invoiceInformation), false) !!}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            </tbody>
        </table>

        @if ($invoice->type === \App\Enums\InvoiceType::INVOICE)
            <table class="invoice-content" cellpadding="0" cellspacing="0">
                <tr class="heading">
                    <td>
                        Item
                    </td>
                    <td>Type</td>
                    <td>Unit price</td>
                    <td>Quantity</td>
                    @if ($location?->vat_percentage)
                        <td>Ex vat</td>
                        <td>Vat</td>
                    @endif
                    <td>
                        Total
                    </td>
                </tr>
                @foreach($invoice->invoiceItems as $item)
                    @php
                        $vat = $item->vat;

                        if ($location?->vat_percentage && is_null($item->vat)) {
                            $vat = $location?->vat_percentage ;
                        } elseif ($location?->vat_percentage && ! is_null($item->vat)) {
                            $vat = $item->vat;
                        }
                    @endphp

                    <tr class="item">
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->discriminator }}</td>
                        <td>{{ $item->unitPrice }}</td>
                        <td>{{ $item->quantity }}</td>

                        @if($location?->vat)
                            <td>{{ $item->ex_vat_amount }}</td>
                            <td>{{ $item->vat_amount }} <small>({{ $vat }}%)</small></td>
                        @endif

                        <td>{{ $item->amount }}</td>
                    </tr>
                @endforeach

                <tr class="total">
                    <td>Totals</td>
                    <td></td>
                    <td></td>
                    <td></td>

                    @if($location?->vat_percentage)
                        <td>{{ $location->tenant->memberCurrency->code }} {{ $invoice->ex_vat_total }}</td>
                        <td>{{ $location->tenant->memberCurrency->code }} {{ $invoice->vat_total }}</td>
                    @endif

                    <td>{{ $location->tenant->memberCurrency->code }} {{ $invoice->amount }}</td>
                </tr>
            </table>

            @if($location?->show_invoice_totals)
                <table>
                    <tbody>
                    <tr>
                        <td>
                            <strong>Opening Balance:</strong> {{ $location->tenant->memberCurrency->code }} {{  number_format($openingBalance, 2, '.', '') }}
                        </td>
                    </tr>

                    @if ($invoice->payment_total > 0)
                        <tr>
                            <td>
                                <strong>Paid:</strong> {{ $location->tenant->memberCurrency->code }} {{ $invoice->payment_total }}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td>
                            <strong>Total Outstanding Balance:</strong> {{ $location->tenant->memberCurrency->code }} {{ number_format(($openingBalance + $invoice->amount) - $invoice->payment_total, 2, '.', '')   }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            @endif

        @else
            <table class="invoice-content" cellpadding="0" cellspacing="0">
                <tr class="heading">
                    <td>
                        Item
                    </td>
                    <td>
                        Total
                    </td>
                </tr>

                <tr class="item">
                    <td>{{ $invoice->description }}</td>
                    <td>{{ $invoice->amount }}</td>
                </tr>

                <tr class="total">
                    <td>Totals</td>
                    <td>
                        {{ $location->tenant->memberCurrency->code }} {{ $invoice->amount }}
                    </td>
                </tr>
            </table>
        @endif

        @if ($invoice->description)
            <p>Description: {{ $invoice->description }}</p>
        @endif

        @if ($invoice->note)
            <p>Notes: {{ $invoice->note }}</p>
        @endif

        @if ($isDownload)
            <div class="align-center">
                <img src="https://octiv-prod-public-newy2432.eu-central-1.linodeobjects.com/email-images/logo-on-light.png" class="octiv-logo" />
            </div>
        @endif
    </div>

</x-layouts.pdf>
