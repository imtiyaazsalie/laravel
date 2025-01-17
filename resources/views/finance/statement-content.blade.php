<div class="invoice-container">
    <table cellpadding="0" cellspacing="0">
        <tbody>
        <tr>
            <td>
                <table>
                    <tr>
                        @if($locationLogo)
                            <td class="align-center-small">
                                <img src="{{ $locationLogo }}" class="studio-logo">
                            </td>
                        @endif
                        <td class="align-center-small">
                            <strong>Statement: {{ $startDate }} - {{ $endDate }}</strong><br><br>
                            Member: {{ $user->full_name }}<br>
                            Email: {{ $user->email }}<br>
                            @if(isset($userTenant) && $userTenant?->member_id)
                                Member id: {{ $userTenant->member_id }}<br>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @if($location)
            <tr>
                <td>
                    <table>
                        <tr>
                            <td class="align-center-small">
                                <strong>{{ $location->business_name }}</strong>
                                <br>
                                @if($location->vat)
                                    Vat number: {{ $location->vat }}
                                @endif
                                {!! nl2br(e($location->invoiceInformation), false) !!}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif
        </tbody>
    </table>

    <table class="invoice-content" cellpadding="0" cellspacing="0">
        <thead>
        <tr class="heading">
            <td class="margin-top-small">Date</td>
            <td class="margin-top-small">Description</td>
            <td class="margin-top-small">Payment</td>
            <td class="margin-top-small">Invoice Amount</td>
            <td class="margin-top-small">Balance</td>
        </tr>
        </thead>

        <tbody>
        <tr>
            <td></td>
            <td>Opening balance</td>
            <td></td>
            <td></td>
            <td>{{ $statement->currencyCode }} {{ $statement->openingBalance }}</td>
        </tr>
        @if($statement->transactions > 0)
            @foreach($statement->transactions as $transaction)
                <tr class="item">
                    <td>{{ $transaction->date }}</td>
                    <td>{{ $transaction->description }}</td>
                    <td>
                        @if(isset($transaction->payment_amount))
                            {{$transaction->payment_amount }}
                        @endif
                    </td>
                    <td>
                        @if(isset($transaction->invoice_amount))
                            @if($transaction->type == 'invoice')
                                {{$transaction->invoice_amount }}
                            @elseif($transaction->type == 'credit_note')
                                -{{$transaction->invoice_amount}}
                            @endif
                        @endif
                    </td>
                    <td>{{ $statement->currencyCode }} {{ $transaction->balance }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>{{ $statement->currencyCode }} {{ $statement->total }}</td>
            </tr>
        @else
            <tr>
                <td class="no-data" style="text-align: center" colspan="5">No transactions</td>
            </tr>
        @endif
        </tbody>
    </table>

    @if(isset($isDownload))
    <div class="align-center">
        <img src="https://octiv-prod-public-newy2432.eu-central-1.linodeobjects.com/email-images/logo-on-light.png" class="octiv-logo"/>
    </div>
    @endif
</div>