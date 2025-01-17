<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\Timezone **/
class TimezoneResource extends JsonResource
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
            'description' => $this->description,
            'abbr' => $this->abbr,
            'zone' => $this->zone,
            'offset' => $this->offset,

            'is_daylight_saving' => $this->is_daylight_saving,
        ];
    }
}
