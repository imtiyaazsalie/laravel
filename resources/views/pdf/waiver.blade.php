<x-layouts.pdf>
    @php
        app()->setLocale($waiver->location->tenant->settings->locale_language);
    @endphp

    <x-slot:title>
        {{ __('waivers.waiverFor') }} {{ $waiver->user->full_name }}
    </x-slot:title>

    <h2 style="margin-bottom: 5px;">{{ $waiver->user->full_name }}</h2>

    <div>
        <table width="100%">
            <tr>
                <td>{{ __('waivers.name&Surname') }}</td>
                <td><strong>{{ $waiver->user->full_name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('waivers.email') }}</td>
                <td><strong>{{ $waiver->user->email }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('waivers.mobile') }}</td>
                <td><strong>{{ $waiver->user->mobile }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('waivers.dateOfBirth') }}</td>
                <td><strong>{{ $waiver->user->dob ? $waiver->user->dob?->toDateString() : 'N/A' }}</strong></td>
            </tr>

            <tr>
                <td>{{ __('waivers.socialSecurityNumber') }}</td>
                <td><strong>{{ $waiver->user->id_number ? $waiver->user->id_number : 'N/A' }}</strong></td>
            </tr>

            <tr>
                <td>{{ __('waivers.userAddress') }}</td>
                <td><strong>{{ $waiver->user->address ? $waiver->user->address : 'N/A' }}</strong></td>
            </tr>

            @if ($waiver->user->member_id)
                <tr>
                    <td>{{ __('waivers.memberId') }}</td>
                    <td><strong>{{ $waiver->user->member_id }}</strong></td>
                </tr>
            @endif

            <tr>
                <td>{{ __('waivers.facility') }}</td>
                <td><strong>{{ $waiver->location->tenant->name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('waivers.location') }}</td>
                <td><strong>{{ $waiver->location->name }}</strong></td>
            </tr>
            <tr>
                <td>{{ __('waivers.locationAddress') }}</td>
                <td><strong>{{ $waiver->location->addresses()->first()->full_address ?? 'n/a' }}</strong></td>
            </tr>

            @if ($waiver->ip_address)
                <tr>
                    <td>{{ __('waivers.ipAddress') }}</td>
                    <td><strong>{{ $waiver->ip_address }}</strong></td>
                </tr>
            @endif

            <tr>
                <td>{{ __('waivers.termsAndConditionsAcceptedOn') }}</td>
                <td><strong>{{ $waiver->signed_on?->toDateTimeString() }}</strong></td>
            </tr>

            @if ($waiver->user->emergency_contact_name)
                <tr>
                    <td>{{ __('waivers.emergencyContactName') }}</td>
                    <td><strong>{{ $waiver->user->emergency_contact_name }}</strong></td>
                </tr>
                <tr>
                    <td>{{ __('waivers.emergencyContactMobile') }}</td>
                    <td><strong>{{ $waiver->user->emergency_contact_mobile }}</strong></td>
                </tr>
            @endif
        </table>

        @if ($bankingDetails)
            <h3>{{ __('waivers.bankingDetails') }}</h3>

            <table width="100%">
                <tr>
                    <td>{{ __('waivers.bankName') }}</td>
                    <td><strong>{{ $bankingDetails->bank->name }}</strong></td>
                </tr>
                <tr>
                    <td>{{ __('waivers.accountType') }}</td>
                    <td><strong>{{ $bankingDetails->account_type->toString() }}</strong></td>
                </tr>
                <tr>
                    <td>{{ __('waivers.accountNumber') }}</td>
                    <td><strong>{{ $bankingDetails->account_no }}</strong></td>
                </tr>
                <tr>
                    <td>{{ __('waivers.accountHolderName') }}</td>
                    <td><strong>{{ $bankingDetails->account_name }}</strong></td>
                </tr>
                <tr>
                    <td>{{ __('waivers.debitDay') }}</td>
                    <td><strong>{{ $bankingDetails->debitDay->name }}</strong></td>
                </tr>
            </table>
        @endif


        @if ($userPackages)
            <h3>{{ __('waivers.packageDetails') }}</h3>

            <table width="100%">
                <thead>
                <tr>
                    <th align="left">{{ __('waivers.packageName') }}</th>
                    <th align="left">{{ __('waivers.start&EndDate') }}</th>
                    <th align="left">{{ __('waivers.price') }}</th>
                </tr>
                </thead>

                <tbody>
                @foreach ($userPackages as $userPackage)
                    <tr>
                        <td align="left">{{ $userPackage->package->name }} {{ $userPackage->sessionsAvailableAsText() }} {{ $userPackage->package->type->toString()}}</td>
                        <td align="left">{{ $userPackage->effective_date->toDateString() }} / {{ $userPackage->end_date ? $userPackage->end_date->toDateString() : 'Open' }}</td>
                        <td align="left">{{ $waiver->location->tenant->memberCurrency->code }} {{ $userPackage->package->price }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <br>

            <table width="100%">
                <tr>
                    <td>{{ __('waivers.membershipTotal') }}</td>
                    <td><strong>{{ $waiver->location->tenant->memberCurrency->code }} {{ $memberFee }}</strong>
                    </td>
                </tr>

                <tr>
                    <td>{{ __('waivers.isMemberOnSpecialRateOrDiscount') }}</td>
                    <td><strong>{{ $isUserOnSpecialRateOrDiscount }}</td>
                </tr>
            </table>


        @endif

        <br>

        @if ($waiver->digital_terms_and_conditions)
            {!! $waiver->digital_terms_and_conditions !!}
        @endif

    </div>




</x-layouts.pdf>
