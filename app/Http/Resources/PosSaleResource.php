<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\PosSale **/
class PosSaleResource extends JsonResource
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
            'status' => $this->status->toArray(),
            'note' => $this->note,
            'discount_amount' => $this->discount_amount,
            'otp_verified' => $this->opt_verified,

            $this->mergeWhen(is_null($this->purchaser_id), [
                'guest' => [
                    'name' => $this->purchaser_name,
                    'contact_number' => $this->purchaser_contact_number,
                    'email' => $this->purchaser_email,
                ],
            ]),

            'purchaser_id' => $this->purchaser_id,
            'purchaser' => new UserResource($this->whenLoaded('purchaser')),

            'invoice_id' => $this->invoice_id,
            'invoice' => new UserInvoiceResource($this->whenLoaded('invoice')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'seller_id' => $this->seller_id,
            'seller' => new UserResource($this->whenLoaded('seller')),

            'items' => PosSaleItemResource::collection($this->whenLoaded('saleItems')),

            'created_at' => $this->created_on?->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
