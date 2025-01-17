<x-layouts.pdf>

    <x-slot:title>
        Invoice for {{ $invoice->created_on->format('d M y') }}
    </x-slot>

    <div class="invoice-box">
        <table class="invoice-header" cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                <div class="box-logo margin-top-small" style="width: initial;">
                                    <img src="{{ asset('image/logo-dark.png') }}" style="max-height:150px; max-width:300px;">
                                </div>

                                <div class="clearfix" style="clear: both;">
                                    <br>
                                </div>

                                <div style="font-size: 1rem; line-height: 1.3 !important;">
                                    <stong class="bold">BOXCHAMP (PTY) LTD</stong>
                                    <br>
                                    BG 13 The Orangerie<br>
                                    72 Orange Street<br>
                                    Gardens<br>
                                    Cape Town<br>
                                    8001
                                </div>
                            </td>

                            <td style="font-size: 1rem; line-height: 1.3;">
                                Invoice #: {{ $invoice->code }}<br>
                                Created: {{ $invoice->created_on->format('d M y') }}<br>
                                Due: {{ $invoice->due_on->format('d M y') }}<br>
                                Location: {{ $invoice->location->name }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="invoice-content" cellpadding="0" cellspacing="0">
            <tr class="heading">
                <td>Item</td>
                @if($invoice->location->tenant->billingCurrency->code !== 'ZAR')
                    <td style="text-align: right;">Total {{ currencyCode }}</td>
                @endif
                <td>Total ZAR</td>
            </tr>

            @foreach($invoice->items as $item)
                <tr class="item">
                    <td>{{ $item->description }}</td>
                    @if($invoice->location->tenant->billingCurrency->code !== 'ZAR')
                        <td style="text-align: right;">{{ $item->amount }}</td>
                        <td>{{ $item->amount_in_rands }}</td>
                    @else
                        <td>{{ $item->amount }}</td>
                    @endif
                </tr>
            @endforeach

            <tr class="total">
                <td>Totals</td>
                @if($invoice->location->tenant->billingCurrency->code !== 'ZAR')
                    <td style="text-align: right;">{{ ($invoice->location->tenant->billingCurrency->code }} {{ {{ $invoice->total }} }}</td>
                    <td>ZAR {{ $invoice->total_in_rands }}</td>
                @else
                    <td>ZAR {{ $invoice->total }}</td>
                @endif
            </tr>

            @if($invoice->location->tenant->billingCurrency->code !== 'ZAR' && $invoice->amount !== 0)
                <tr>
                    <td colspan="4" style="text-align: center;">
                        Exchange rate: ({{ $invoice->location->tenant->billingCurrency->code }} 1 = ZAR {{ $invoice->exchange_rate }} at {{ $invoice->created_on->format('Y-m-d, H:i:s') }}
                    </td>
                </tr>
            @endif
        </table>

        <center><p style="text-align:center">Powered by:<br><img src="{{ asset('image/invoice.png') }}"/></p></center>
    </div>


</x-layouts.pdf>

