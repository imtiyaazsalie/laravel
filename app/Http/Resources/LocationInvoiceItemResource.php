<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\LocationInvoiceItem **/
class LocationInvoiceItemResource extends JsonResource
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
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'discriminator' => $this->discriminator->toArray(),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'invoice_id' => $this->invoice_id,
            'invoice' => new LocationInvoiceResource($this->whenLoaded('invoice')),

            'updated_at' => $this->updated_on->toDateTimeString(),
            'created_at' => $this->created_on->toDateTimeString(),

            'deleted' => $this->deleted,
        ];
    }
}
