<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Bank;
use Illuminate\Http\Request;

/** @mixin Bank * */
class BanksResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,
            'code' => $this->universal_code,
            'import_code' => $this->import_code,

            'country_id' => $this->country_id,
            'country' => new CountryResource($this->whenLoaded('country')),
        ];
    }
}
