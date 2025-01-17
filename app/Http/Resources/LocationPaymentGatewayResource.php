<?php

namespace App\Http\Resources;

use App\Enums\PaymentGateway;
use App\Helpers\JsonResource;
use App\Models\LocationPaymentGateway;
use Illuminate\Http\Request;

/** @mixin LocationPaymentGateway * */
class LocationPaymentGatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $settings = null;

        if (! is_null($this->location_payment_gateway_settings)) {
            $settings = $this->payment_gateway_id === PaymentGateway::THREE_PEAKS->value ? $this->location_payment_gateway_settings : new LocationPaymentGatewaySettingsResource($this->location_payment_gateway_settings);
        }

        return [
            'id' => $this->getKey(),

            'credentials' => $this->credentials,
            'context' => $this->context,
            'is_active' => $this->is_active,

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_gateway' => new PaymentGatewayResource($this->whenLoaded('paymentGateway')),

            'settings' => $settings,

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
        ];
    }
}
