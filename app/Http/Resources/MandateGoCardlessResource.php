<?php

namespace App\Http\Resources;

use App\Models\MandateGoCardless;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MandateGoCardless * */
class MandateGoCardlessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'location_payment_gateway' => new LocationPaymentGatewayResource($this->whenLoaded('locationPaymentGateway')),
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'created_at' => $this->created_on?->toDateString(),
            'updated_at' => $this->updated_on?->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toDateString(),
            'mandate' => $this->mandate,
            'customer' => $this->customer,
            'cancel_reason' => $this->cancel_reason,
            'redirect_flow_id' => $this->redirect_flow_id,
            'note' => $this->note,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
        ];
    }
}
