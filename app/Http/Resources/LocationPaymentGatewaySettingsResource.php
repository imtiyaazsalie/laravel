<?php

namespace App\Http\Resources;

use App\Enums\PaymentGateway;
use App\Helpers\JsonResource;
use App\Models\LocationPaymentGatewaySettings;
use App\Services\PaymentGateways\PaystackService;

/** @mixin LocationPaymentGatewaySettings * */
class LocationPaymentGatewaySettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $settings = [
            'id' => $this->getKey(),
            'public_token' => $this->public_token,
            'is_for_sign_up' => $this->for_sign_up,
            'location_payment_gateway_id' => $this->location_payment_gateway_id,
            'location_payment_gateway' => new LocationPaymentGatewayResource($this->whenLoaded('locationPaymentGateway')),
            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'updated_at' => $this->modified_on?->toDateTimeString(),
        ];

        if ($this->locationPaymentGateway?->payment_gateway_id === PaymentGateway::SAGE_PAY_V3->value) {
            $settings = array_merge($settings, [
                'username' => $this->username,
                'password' => $this->password,
                'pin' => $this->pin,
                'merchant_account_number' => $this->merchant_account_number,
                'debit_order_service_key' => $this->debit_order_service_key,
                'account_service_key' => $this->account_service_key,
                'pay_now_service_key' => $this->pay_now_service_key,
            ]);
        }

        if ($this->locationPaymentGateway?->payment_gateway_id === PaymentGateway::GO_CARDLESS->value) {
            $settings = array_merge($settings, [
                'token' => $this->token,
                'organisation_id' => $this->organisation_id,
                'disconnected_from_event' => $this->disconnected_from_event,
            ]);
        }

        if ($this->locationPaymentGateway?->payment_gateway_id === PaymentGateway::STRIPE->value) {
            $settings = array_merge($settings, [
                'secret_key' => $this->secret_key,
                'public_key' => $this->public_key,
            ]);
        }

        if ($this->locationPaymentGateway?->payment_gateway_id === PaymentGateway::PAYSTACK->value) {
            $subaccountData = $this->sub_account_code ? (new PaystackService())->fetchSubaccount($this->sub_account_code) : null;

            $settings = array_merge($settings, [
                'sub_account_id' => $this->sub_account_id,
                'sub_account_code' => $this->sub_account_code,
                'bank_name' => $subaccountData['bank_name'] ?? null,
                'account_number' => $subaccountData['account_number'] ?? null,
                'notify_email_address' => $subaccountData['primary_contact_email'] ?? null,
            ]);
        }

        if ($this->locationPaymentGateway?->payment_gateway_id === PaymentGateway::SEPA->value) {
            $settings = array_merge($settings, [
                'creditor_id' => $this->creditor_id,
                'creditor_iban' => $this->creditor_iban,
                'creditor_bic' => $this->creditor_bic,
            ]);
        }

        return $settings;
    }
}
