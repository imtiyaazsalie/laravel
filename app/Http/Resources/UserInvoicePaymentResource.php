<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\UserInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** @mixin UserInvoicePayment * */
class UserInvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'date_time' => Carbon::parse($this->date_time)->format('Y-m-d'),
            'notes' => $this->notes,
            'type' => $this->type,

            'user_to_facility_id' => $this->user_to_facility_id,

            'invoice_id' => $this->invoice_id,
            'invoice' => new UserInvoiceResource($this->whenLoaded('invoice')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDateTimeString(),
            'deleted' => $this->deleted,

            'tags' => TagResource::collection($this->tags),
        ];
    }
}
