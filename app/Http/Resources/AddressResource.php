<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $addressStructureOrdered = array_intersect_key($this->structured_address, array_flip(['complex_details', 'address_number', 'street', 'block', 'place', 'region', 'postcode', 'locality', 'neighborhood', 'country']));

        return [
            'id' => $this->id,
            'full_address' => $this->full_address,
            'coordinates' => $this->coordinates,
            'structured_address' => $addressStructureOrdered,
            'addressable_id' => $this->addressable_id,
            'addressable_type' => $this->addressable_type,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
