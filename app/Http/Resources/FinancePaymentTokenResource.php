<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\FinancePaymentToken;
use Illuminate\Http\Request;

/** @mixin FinancePaymentToken * */
class FinancePaymentTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'user_id' => $this->user_id,
            'user' => new UserMinimalResource($this->whenLoaded('user')),

            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_gateway' => new PaymentGatewayResource($this->whenLoaded('paymentGateway')),

            'customer_id' => $this->customer_id,
            'setup_intent_id' => $this->setup_intent_id,
            'payment_method' => $this->payment_method,
            'token' => $this->token,
            'default' => $this->default,
            'type' => $this->type,

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
