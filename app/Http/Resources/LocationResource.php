<?php

namespace App\Http\Resources;

use App\Enums\FinancePaymentTokenType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Helpers\JsonResource;
use App\Models\Location;
use App\Services\PaymentTokenService;
use Illuminate\Http\Request;

/** @mixin Location * */
class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $paymentToken = null;

        if (auth()->user()?->isAdmin()) {
            $this->append('activeMembersCount');
        }

        if ((str($request->input('append'))->contains('finance_payment_token'))) {
            $paymentToken = (new PaymentTokenService())->getFinancePaymentToken(
                paymentMethods: ['card'],
                financePaymentTokenType: FinancePaymentTokenType::LOCATION,
                location: $this->getKey(),
                paymentGateway: $this->billing_payment_gateway_id,
            );
        }

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'business_name' => $this->business_name,
            'vat' => $this->vat_number,
            'invoice_information' => $this->invoice_information,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'phone_number' => $this->phone_number,
            'attendance_code' => $this->attendance_code,
            'image_one' => $this->image_one,
            'image_two' => $this->image_two,
            'image_three' => $this->image_three,
            'image_four' => $this->image_four,
            'prefix' => $this->prefix,
            'deactivated_at' => $this->deactivated_on?->toDateTimeString(),
            'can_debit' => $this->can_debit,
            'is_active' => $this->is_active,
            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'billing_payment_gateway_id' => $this->billing_payment_gateway_id,
            'billing_payment_gateway' => new PaymentGatewayResource($this->whenLoaded('billingPaymentGateway')),
            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_gateway' => new PaymentGatewayResource($this->whenLoaded('paymentGateway')),
            'timezone_id' => $this->timezone_id,
            'timezone' => new TimezoneResource($this->whenLoaded('timezone')),
            'category_id' => $this->category_id,
            'category' => new LocationCategoryResource($this->whenLoaded('category')),
            'health_providers' => HealthProviderResource::collection($this->whenLoaded('healthProviders')),
            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),
            'address_id' => $this->addresses?->getKey(),
            'address' => new AddressResource($this->whenLoaded('addresses')),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),

            'active_members_count' => $this->whenAppended('activeMembersCount'),

            $this->mergeWhen(isset($this->is_checked), [
                'is_checked' => $this->is_checked,
            ]),

            'finance_payment_token' => $paymentToken ? new FinancePaymentTokenResource($paymentToken) : null,

            'locationPaymentGateways' => $this->when($this->relationLoaded('locationPaymentGateways'), function () {
                return $this->locationPaymentGateways()->where('is_active', true)->get();
            }),

            'debitOrderLocationPaymentGateway' => $this->when($this->relationLoaded('locationPaymentGateways'), function () {
                $locationPaymentGateway = $this->locationPaymentGateways()
                    ->where('context', '=', PaymentGatewayContext::DEBIT_ORDER)
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                if ($locationPaymentGateway) {
                    if (in_array($locationPaymentGateway->paymentGateway->getKey(), [PaymentGateway::SAGE_PAY_V2->value, PaymentGateway::SAGE_PAY_V3->value])) {
                        $paymentGatewayCredentials = explode('&', $locationPaymentGateway->credentials);

                        return [
                            'id' => $this->paymentGateway->getKey(),
                            'name' => $this->paymentGateway->name,
                            'netcashMerchantAccountNumber' => ($paymentGatewayCredentials[0] ?? null),
                            'netcashAccountServiceKey' => ($paymentGatewayCredentials[1] ?? null),
                            'netcashDebitOrderServiceKey' => ($paymentGatewayCredentials[2] ?? null),
                        ];
                    }

                    if ($locationPaymentGateway->paymentGateway->getKey() === PaymentGateway::THREE_PEAKS->value) {
                        $paymentGatewayCredentials = explode('&', $locationPaymentGateway->credentials);

                        return [
                            'id' => $this->paymentGateway->getKey(),
                            'name' => $this->paymentGateway->name,
                            'threePeaksDevId' => $paymentGatewayCredentials[0] ?? null,
                            'threePeaksDevToken' => $paymentGatewayCredentials[1] ?? null,
                            'threePeaksCref' => $paymentGatewayCredentials[2] ?? null,
                        ];
                    }
                }

                return $locationPaymentGateway;
            }),

            // TODO: check if below is actually used
            // 'display_booking_details' => $this->display_booking_details,
            // 'visible_in_app_sessions' => $this->visible_in_app_sessions,
            // 'view_class_bookings' => $this->view_class_bookings,
            // 'extra_parameters' => $this->when(! in_array('extra_parameters', $this->getHidden()), $this->extra_parameters),
        ];
    }
}
