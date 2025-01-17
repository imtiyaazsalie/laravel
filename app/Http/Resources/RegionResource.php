<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Region **/
class RegionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'country_code_iso2' => $this->country_code_iso2,
            'is_active' => $this->is_active,
            'currencies' => CurrencyResource::collection($this->whenLoaded('currencies')),
            'timezones' => TimezoneResource::collection($this->whenLoaded('timezones')),
        ];
    }
}