<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\LocationInvoice **/
class LocationInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'amount' => $this->amount,
            'amount_in_rands' => $this->amount_in_rands,
            'type' => $this->type,
            'due_at' => $this->due_on?->toDateTimeString(),
            //TODO: Figure out what these do
            'period_start' => $this->period_start?->toDateTimeString(),
            'period_end' => $this->period_end?->toDateTimeString(),
            'sent_at' => $this->sent_on?->toDateTimeString(),
            'notes' => $this->note,

            $this->whenAppended('outstanding_amount', [
                'outstanding_amount' => $this->outstanding_amount,
            ]),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'parent_id' => $this->parent_id,
            'parent' => new LocationInvoiceResource($this->whenLoaded('parent')),

            'items' => LocationInvoiceItemResource::collection($this->whenLoaded('invoiceItems')),
            'payments' => LocationPaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
