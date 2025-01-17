<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\DebitDayDate **/
class DebitDayDateResource extends JsonResource
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

            'date' => $this->debit_day_date->toDateTimeString(),
            'is_active' => $this->is_active,

            'debit_day_id' => $this->debit_day_id,
            'debit_day' => new DebitDayResource($this->whenLoaded('debitDay')),
        ];
    }
}
