<x-layouts.pdf>

    @php
        app()->setLocale($contract->tenant->settings->locale_language);
    @endphp
    <x-slot:title>
        {{ $contract->user->full_name }}
    </x-slot>

    <div>
        <table width="100%">
            <tr>
                <td>{{ __('contracts.name&surname') }}</td>
                <td><strong>{{ $contract->user->full_name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.email') }}</td>
                <td><strong>{{ $contract->user->email }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.mobile') }}</td>
                <td><strong>{{ $contract->user->mobile }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.dateOfBirth') }}</td>
                <td><strong>{{ $contract->user->dob?->format('Y-m-d') ?: 'n/a' }}</strong></td>
            </tr>

            <tr>
                <td>{{ __('contracts.socialSecurityNumber') }}</td>
                <td><strong>{{ $contract->user->id_number ?: 'n/a' }}</strong></td>
            </tr>x

            <tr>
                <td>{{ __('contracts.userAddress') }}</td>
                <td><strong>{{ $contract->user->address ?: 'n/a' }}</strong></td>
            </tr>

            @if($contract->membership)
                <tr>
                    <td>{{ __('contracts.memberId') }}/td>
                    <td><strong>{{ $contract->membership->getKey() }}</strong></td>
                </tr>
            @endif

            <tr>
                <td>{{ __('contracts.facility') }}</td>
                <td><strong>{{ $contract->tenant->name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.location') }}</td>
                <td><strong>{{ $contract->location->name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.locationAddress') }}</td>
                <td><strong>{{ $contract->location->addresses()->first()->full_address ?? 'n/a' }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('contracts.contractPeriod') }}</td>
                <td><strong>{{ $contract->periodAsString() }}</strong></td>
            </tr>

            @if($contract->ip_address)
                <tr>
                    <td>{{ __('contracts.ipAddress') }}</td>
                    <td><strong>{{ $contract->ip_address }}</strong></td>
                </tr>
            @endif

            <tr>
                <td>{{ __('contracts.termsAndConditionsAcceptedOn') }}</td>
                <td><strong>{{ $contract->accepted_on?->format('Y-m-d') ?: 'n/a' }}</strong></td>
            </tr>
        </table>

        @if($contract->tenantUser?->bankAccount)
            <h3>{{ __('contracts.bankingDetails') }}</h3>

            <table width="100%">
                @if(in_array($contract->location->payment_gateway_id, [\App\Enums\PaymentGateway::SAGE_PAY_V2->value,\App\Enums\PaymentGateway::SAGE_PAY_V3->value,\App\Enums\PaymentGateway::THREE_PEAKS->value]))
                    <tr>
                        <td>{{ __('contracts.bankName') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->bank->name }}</strong></td>
                    </tr>
                    <tr>
                        <td>{{ __('contracts.accountType') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->accountType->toString() }}</strong></td>
                    </tr>
                    <tr>
                        <td>{{ __('contracts.accountNumber') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->account_no }}</strong></td>
                    </tr>
                    <tr>
                        <td>{{ __('contracts.accountHolderName') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->account_name }}</strong></td>
                    </tr>
                @endif

                @if($contract->location->payment_gateway_id === \App\Enums\PaymentGateway::SEPA->value)
                    <tr>
                        <td>{{ __('contracts.iban') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->iban }}</strong></td>
                    </tr>
                    <tr>
                        <td>{{ __('contracts.bic') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->bic }}</strong></td>
                    </tr>
                    <tr>
                        <td>{{ __('contracts.address') }}</td>
                        <td><strong>{{ $contract->tenantUser->bankAccount->address ?? 'N/A' }}</strong></td>
                    </tr>
                @endif

                <tr>
                    <td>{{ __('contracts.debitDay') }}</td>
                    <td><strong>{{ trans('contracts.debit_day.' . $contract->tenantUser->bankAccount->debitDay->getKey()) }}</strong></td>
                </tr>
            </table>
        @endif

        @if($userPackages->count())
            <h3>{{ __('contracts.packageDetails') }}</h3>

            <table width="100%">
                <thead>
                <tr>
                    <th align="left">{{ __('contracts.packageName') }}</th>
                    <th align="left">{{ __('contracts.start&EndDate') }}</th>
                    <th align="left">{{ __('contracts.price') }}</th>
                </tr>
                </thead>

                <tbody>
                @foreach ($userPackages as $userPackage)
                    <tr>
                        <td align="left">
                            <div>{{ $userPackage->package->name }} {{ $userPackage->package->type->toString() }}</div>
                            <div style="font-size: 0.9em;">{{ $userPackage->package->description }}</div>
                        </td>
                        <td align="left">{{ $userPackage->effective_date->toDateString() }}
                            / {{ $userPackage->end_date ? $userPackage->end_date->toDateString() : 'Open' }}</td>
                        <td align="left">{{ $contract->tenant->memberCurrency->code }} {{ number_format($userPackage->package->price, 2, '.', '') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <br>

            <table width="100%">
                <tr>
                    <td>{{ __('contracts.membershipTotal') }}</td>
                    <td><strong>{{ $contract->tenant->memberCurrency->code }} {{ $memberFee }}</strong>
                    </td>
                </tr>

                <tr>
                    <td>{{ __('contracts.isMemberOnSpecialRateOrDiscount') }}</td>
                    <td><strong>{{ $isUserOnSpecialRateOrDiscount }}</td>
                </tr>
            </table>
        @endif

        <br>

        {!! $contract->contract_terms_and_conditions !!}
    </div>
</x-layouts.pdf>
