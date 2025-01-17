<?php

namespace App\Services;

use App\Enums\FinancePaymentTokenType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Http\Requests\Location\CreateLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Models\CrmSetting;
use App\Models\DebitBatch;
use App\Models\DebitDayDate;
use App\Models\Location;
use App\Models\LocationHealthProvider;
use App\Models\LocationInvoice;
use App\Models\LocationInvoiceItem;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\FileBag;

class LocationService
{
    /**
     * Create location from request data.
     */
    public function createFromRequest(CreateLocationRequest $request): Location
    {
        $locationData = $request->safe()->only([
            'tenant_id',
            'name',
            'prefix',
            'business_name',
            'description',
            'address',
            'latitude',
            'longitude',
            'phone_number',
            'timezone_id',
            'category_id',
            'billing_payment_gateway_id',
            'payment_gateway_id',
            'can_debit',
        ]);

        $location = $this->store($locationData, $request->files);

        if ($request->has('health_provider_ids') && is_array($request->input('health_provider_ids'))) {
            $this->storeHealthProviderById($location, $request->validated('health_provider_ids'));
        }

        if ($request->has('amenity_ids')) {
            $location->amenities()->sync($request->amenity_ids);
        }

        $location->load('amenities');

        if (in_array($request->payment_gateway_id, [PaymentGateway::SAGE_PAY_V3->value, PaymentGateway::THREE_PEAKS->value])) {
            $locationPaymentGatewayData = $request->safe()->only([
                'payment_gateway_id',
                'sage_merchant_account_number',
                'sage_account_service_key',
                'sage_debit_order_service_key',
                'three_peaks_dev_id',
                'three_peaks_dev_token',
                'three_peaks_cref',
            ]);

            $locationPaymentGatewayData['box_facility_id'] = $location->getKey();
            $locationPaymentGatewayData['context'] = 'debit_order';

            $this->storePaymentGateway($locationPaymentGatewayData);
        }

        if ($request->get('payment_gateway_id') !== PaymentGateway::NO_GATEWAY->value) {
            $this->storeFutureDebitBatches($location);
        }

        CrmSetting::create([
            'box_facility_id' => $location->getKey(),
            'sms_status' => 'disabled',
            'email_status' => 'enabled',
        ]);

        return $location;
    }

    /**
     * Update location from request data.
     */
    public function updateFromRequest(Location $location, UpdateLocationRequest $request): Location
    {
        $locationData = $request->safe()->only([
            'name',
            'prefix',
            'business_name',
            'description',
            'address',
            'latitude',
            'longitude',
            'phone_number',
            'timezone_id',
            'category_id',
            'billing_payment_gateway_id',
            'payment_gateway_id',
            'can_debit',
        ]);

        $locationPaymentGatewayData = $request->only([
            'payment_gateway_id',
            'sage_merchant_account_number',
            'sage_account_service_key',
            'sage_debit_order_service_key',
            'three_peaks_dev_id',
            'three_peaks_dev_token',
            'three_peaks_cref',
        ]);

        $locationPaymentGatewayData['box_facility_id'] = $location->getKey();
        $locationPaymentGatewayData['context'] = 'debit_order';

        $this->updatePaymentGateway($location, $locationPaymentGatewayData);

        if ($location->billing_payment_gateway_id !== PaymentGateway::NO_GATEWAY && ($location->billing_payment_gateway_id->value !== $request->input('billing_payment_gateway_id'))) {
            (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: ['card'],
                financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                location: $location,
                paymentGateway: $location->billing_payment_gateway_id
            )?->delete();
        }

        $location = $this->update($location, $locationData, $request->files);

        if ($request->has('health_provider_ids')) {
            $this->syncHealthProviderById($location, $request->validated('health_provider_ids'));
        }

        if ($request->has('amenity_ids')) {
            $location->amenities()->sync($request->amenity_ids);
        }

        $location->load('amenities');

        $location->refresh();

        return $location;
    }

    public function store($data, FileBag $file): Location
    {
        /** @var Location $location */
        $location = Location::query()->create($data);

        if ($file->count() >= 1) {
            $this->uploadLocationImage($location, $file);
        }

        return $location;
    }

    public function uploadLocationImage(Location $location, FileBag $file): void
    {
        if ($file->has('image_one')) {
            $image = Storage::putFileAs(
                'location-images/'.$location->getKey(),
                $file->get('image_one'),
                $file->get('image_one')->getClientOriginalName(),
                ['visibility' => 'public']
            );

            $location->image_one = Storage::url($image);
            $location->save();
        }

        if ($file->has('image_two')) {
            $image = Storage::putFileAs(
                'location-images/'.$location->getKey(),
                $file->get('image_two'),
                $file->get('image_two')->getClientOriginalName(),
                ['visibility' => 'public']
            );

            $location->image_two = Storage::url($image);
            $location->save();
        }

        if ($file->has('image_three')) {
            $image = Storage::putFileAs(
                'location-images/'.$location->getKey(),
                $file->get('image_three'),
                $file->get('image_three')->getClientOriginalName(),
                ['visibility' => 'public']
            );

            $location->image_three = Storage::url($image);
            $location->save();
        }

        if ($file->has('image_four')) {
            $image = Storage::putFileAs(
                'location-images/'.$location->getKey(),
                $file->get('image_four'),
                $file->get('image_four')->getClientOriginalName(),
                ['visibility' => 'public']
            );

            $location->image_four = Storage::url($image);
            $location->save();
        }
    }

    public function storeHealthProviderById(Location $location, array $healthProviderIds): void
    {
        foreach ($healthProviderIds as $healthProviderId) {
            LocationHealthProvider::updateOrCreate(
                [
                    'box_facility_id' => $location->getKey(),
                    'health_provider_id' => $healthProviderId,
                ]
            );
        }
    }

    public function syncHealthProviderById(Location $location, ?array $healthProviderIds): void
    {
        if ($healthProviderIds === null) {
            $location->healthProviders()->detach();

            return;
        }

        $providers = $location->healthProviders()->get();

        $providers->filter(function ($healthProvider) use ($location, $healthProviderIds) {
            //delete providers not in the array
            if (! in_array($healthProvider->getKey(), $healthProviderIds)) {
                $location->healthProviders()->detach($healthProvider);

                return false;
            }

            return true;
        });

        //add providers in the array but not in the database
        foreach ($healthProviderIds as $healthProviderId) {
            if ($providers->doesntContain('id', $healthProviderId)) {
                LocationHealthProvider::create(
                    [
                        'box_facility_id' => $location->getKey(),
                        'health_provider_id' => $healthProviderId,
                    ]
                );
            }
        }
    }

    public function storePaymentGateway($data): void
    {
        if (! in_array($data['payment_gateway_id'], [PaymentGateway::THREE_PEAKS->value, PaymentGateway::SAGE_PAY_V3->value])) {
            return;
        }

        if ($data['payment_gateway_id'] == PaymentGateway::SAGE_PAY_V3->value) {
            $data['credentials'] = $data['sage_merchant_account_number'].'&'.$data['sage_account_service_key'].'&'.$data['sage_debit_order_service_key'];
        } else {
            $data['credentials'] = $data['three_peaks_dev_id'].'&'.$data['three_peaks_dev_token'].'&'.$data['three_peaks_cref'];
        }

        $locationPaymentGateway = new LocationPaymentGateway();

        $locationPaymentGateway->fill(Arr::only($data, ['box_facility_id', 'payment_gateway_id', 'context', 'credentials']));
        $locationPaymentGateway->save();

        // Create settings if there are none or if new $boxFacilityPaymentGateway was created
        if ($data['payment_gateway_id'] == PaymentGateway::SAGE_PAY_V3->value && ! $locationPaymentGateway->settings instanceof LocationPaymentGatewaySettings) {
            LocationPaymentGatewaySettings::create([
                'box_facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
                'public_token' => sha1(uniqid(true)),
                'discr' => PaymentGateway::getLocationPaymentGatewayDiscr(PaymentGateway::tryFrom($data['payment_gateway_id'])),
                'merchant_account_number' => $data['sage_merchant_account_number'],
                'account_service_key' => $data['sage_account_service_key'],
                'debit_order_service_key' => $data['sage_debit_order_service_key'],
            ]);
        }
    }

    public function updatePaymentGateway(Location $location, $data): void
    {
        $data['credentials'] = null;
        $paymentGatewayId = $data['payment_gateway_id'];

        if ($paymentGatewayId == PaymentGateway::SAGE_PAY_V3->value) {
            $data['credentials'] = $data['sage_merchant_account_number'].'&'.$data['sage_account_service_key'].'&'.$data['sage_debit_order_service_key'];
        } elseif ($paymentGatewayId == PaymentGateway::THREE_PEAKS->value) {
            $data['credentials'] = $data['three_peaks_dev_id'].'&'.$data['three_peaks_dev_token'].'&'.$data['three_peaks_cref'];
        }

        // Update box facility payment gateway details
        if ($location->paymentGateway->getKey() !== (int) $paymentGatewayId) {
            $context = PaymentGatewayContext::DEBIT_ORDER;

            // Disable payment gateway credentials if box had details before
            $query = LocationPaymentGateway::query()
                ->where('box_facility_id', '=', $location->getKey())
                ->where('is_active', '=', true);

            if ($location->paymentGateway->getKey() === PaymentGateway::GO_CARDLESS->value) {
                $query->where('payment_gateway_id', '=', PaymentGateway::GO_CARDLESS);
                $context = PaymentGatewayContext::AD_HOC;
            }

            $query
                ->where('context', '=', $context)
                ->update(['is_active' => false]);

            // Create new details if payment gateway was set to "No gateway"
            if ($paymentGatewayId !== PaymentGateway::NO_GATEWAY->value) {
                $this->storePaymentGateway($data);
                $this->storeFutureDebitBatches($location);
            }
        } else {
            $locationPaymentGateway = LocationPaymentGateway::query()
                ->where('box_facility_id', '=', $location->getKey())
                ->where('payment_gateway_id', '=', $data['payment_gateway_id'])
                ->where('context', '=', PaymentGatewayContext::DEBIT_ORDER)
                ->latest()
                ->first();

            if (! $locationPaymentGateway) {
                $this->storePaymentGateway($data);
            } else {
                $locationPaymentGateway->update([
                    'credentials' => $data['credentials'],
                    'is_active' => true,
                ]);

                if ($paymentGatewayId == PaymentGateway::SAGE_PAY_V3->value) {
                    $locationPaymentGateway->settings()->updateOrCreate([
                        'merchant_account_number' => $data['sage_merchant_account_number'],
                        'account_service_key' => $data['sage_account_service_key'],
                        'debit_order_service_key' => $data['sage_debit_order_service_key'],
                    ],
                        [
                            'box_facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
                            'public_token' => sha1(uniqid(true)),
                            'discr' => PaymentGateway::getLocationPaymentGatewayDiscr(PaymentGateway::tryFrom($data['payment_gateway_id'])),
                        ]
                    );
                }
            }
        }
    }

    public function storeFutureDebitBatches(Location $location): void
    {
        $offset = Carbon::now()->addDays(5)->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

        $debitDates = DebitDayDate::query()
            ->leftJoin('debit_days', 'debit_days.debit_day_id', 'debit_day_dates.debit_day_id')
            ->where([
                ['debit_days.interval', '=', 'P5D'],
                ['debit_day_dates.debit_day_date', '>', $offset],
            ])
            ->orWhere([
                ['debit_days.interval', '=', 'P0D'],
                ['debit_day_dates.debit_day_date', '>', $tomorrow],
            ])
            ->get();

        foreach ($debitDates as $debitDate) {
            $debitDay = DebitBatch::query()
                ->where('box_facility_id', '=', $location->getKey())
                ->where('debit_day_date_id', '=', $debitDate->debit_day_date_id)
                ->first();

            if (! $debitDay) {
                $debit = new DebitBatch();

                $debit->fill([
                    'box_facility_id' => $location->getKey(),
                    'debit_day_date_id' => $debitDate->debit_day_date_id,
                ]);

                $debit->save();
            }
        }
    }

    public function updateExportParametersByTenantRegion(Location $location, $data): Location
    {
        $location->update([
            'extra_parameters' => [
                'bank_user_code' => $data['bank_user_code'],
                'bank_user_name' => $data['bank_user_name'],
                'bank_nominated_account' => $data['bank_nominated_account'],
            ],
        ]);

        return $location;
    }

    public function update(Location $location, $data, FileBag $file): Location
    {
        $location->update($data);

        if ($file->count() >= 1) {
            $this->uploadLocationImage($location, $file);
        }

        return $location;
    }

    public function delete(Location $location): bool
    {
        return $location->update([
            'is_active' => false,
            'deactivated_on' => now(),
        ]);
    }

    public function getGoCardlessSettings(Location $location): ?LocationPaymentGatewaySettings
    {
        return $location->locationPaymentGateways()
            ->where('box_facility_to_payment_gateway.payment_gateway_id', PaymentGateway::GO_CARDLESS->value)
            ->where('box_facility_to_payment_gateway.context', PaymentGatewayContext::AD_HOC->value)
            ->where('box_facility_to_payment_gateway.is_active', true)
            ->with('settings')
            ->first()
            ?->settings;
    }

    public function getActivePaymentGatewayByContextAndTypeId(Location $location, PaymentGatewayContext $context, PaymentGateway $typeId)
    {
        return $location->locationPaymentGateways()
            ->where('box_facility_to_payment_gateway.context', $context)
            ->where('box_facility_to_payment_gateway.payment_gateway_id', $typeId)
            ->where('box_facility_to_payment_gateway.is_active', true)
            ->orderBy('facility_to_payment_gateway_id', 'desc');
    }

    public function createLocationInvoice(Location $location, float $amount, InvoiceStatus $status = InvoiceStatus::UNPAID): LocationInvoice
    {
        $amountInRands = $amount;
        $currencyCode = $location->tenant->tenantCurrency->code;

        if ($currencyCode !== 'ZAR') {
            $amountInRands = $this->convertAmountToRands($currencyCode, $amount);
        }

        $locationInvoice = LocationInvoice::create([
            'box_facility_id' => $location->getKey(),
            'code' => uniqid('', false),
            'description' => 'Location invoice: '.$location->name,
            'amount' => $amount,
            'amount_in_rands' => $amountInRands,
            'type' => InvoiceType::INVOICE,
            'due_on' => now(),
            'period_start' => now(),
            'period_end' => now(),
            'status' => $status,
        ]);

        $this->createLocationInvoiceItem($location, $locationInvoice->getKey(), $amount);

        return $locationInvoice;
    }

    public function createLocationInvoiceItem(Location $location, int $invoiceId, float $amount): void
    {
        $currencyCode = $location->tenant->tenantCurrency->code;

        LocationInvoiceItem::create([
            'facility_invoice_id' => $invoiceId,
            'code' => null,
            'description' => 'Facility Membership',
            'unit_price' => $amount,
            'quantity' => 1,
            'amount' => $amount,
            'amount_in_rands' => $currencyCode !== 'ZAR' ? $this->convertAmountToRands($currencyCode, $amount) : $amount,
            'discriminator' => 'membership',
        ]);
    }

    public function convertAmountToRands($currencyCode, $amount): ?float
    {
        $response = Http::get('https://apilayer.net/api/convert?access_key='.config('currency-layer.accessKey')."&from=$currencyCode&to=ZAR&amount=$amount&format=1");

        return @$response->json(['result']);
    }
}
