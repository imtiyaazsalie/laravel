<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\UserInvoiceItem **/
class UserInvoiceItemResource extends JsonResource
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
            'discriminator' => $this->discriminator,
            'description' => $this->description,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'code' => $this->code,

            'user_package_id' => $this->user_to_package_id,
            'user_package' => new UserPackageResource($this->whenLoaded('userPackage')),

            'invoice_id' => $this->invoice_id,
            'invoice' => new UserInvoiceResource($this->whenLoaded('invoice')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDatetimeString(),

            'deleted' => $this->deleted,
        ];
    }
}
