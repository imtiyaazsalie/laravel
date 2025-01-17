<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\PosSaleItem **/
class PosSaleItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'sessions_released_at' => $this->sessions_released_on?->toDateTimeString(),

            'stock_item_id' => $this->stock_item_id,
            'stock_item' => new PosStockItemResource($this->whenLoaded('stockItem')),

            'sale_id' => $this->sale_id,
            'sale' => new PosSaleResource($this->whenLoaded('sale')),

            'sessions_released_by_id' => $this->sessions_released_by_id,
            'sessions_released_by' => new UserResource($this->whenLoaded('sessionsReleasedBy')),
        ];
    }
}
