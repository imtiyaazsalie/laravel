<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\PosStockItem **/
class PosStockItemResource extends JsonResource
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

            'name' => $this->name,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'sku' => $this->sku,
            'stock_level' => $this->stock_level,
            'description' => $this->description,
            'image_url' => $this->getImageUrl(),
            'category' => $this->category,
            'vat' => $this->vat,

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'created_at' => $this->created_on->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
