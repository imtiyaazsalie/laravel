<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\Country **/
class CountryResource extends JsonResource
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
            'code' => $this->code,
            'timezone' => $this->timezone,

            'region_id' => $this->region_id,
            'region' => new RegionResource($this->whenLoaded('region')),
        ];
    }
}
