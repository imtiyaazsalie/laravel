<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\MeasurementUnit **/
class MeasurementUnitResource extends JsonResource
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
            'unit' => $this->unit,
            'format' => $this->format,
            'is_active' => $this->is_active,
        ];
    }
}
