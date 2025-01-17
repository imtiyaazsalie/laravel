<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\LocationPayment **/
class LocationPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->box_payment_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status,
            'date_paid' => $this->date_paid,

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'invoice_id' => $this->invoice_id,
            'invoice' => new LocationInvoiceResource($this->whenLoaded('invoice')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
