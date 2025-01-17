<?php

namespace App\Http\Resources\StripeConnect\PaymentMethods;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'location_id' => $this->location_id,
            'payment_method' => $this->payment_method,
            'payment_gateway_id' => $this->payment_gateway_id,
            'token' => $this->token,
            'payment_method_info' => $this->meta,
            'default' => $this->default,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
