<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\LocationAmenity;
use Illuminate\Http\Request;

/** @mixin LocationAmenity */
class LocationAmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        return [
            'id' => $this->getKey(),
            'amenity_id' => $this->amenity_id,
            'amenity' => new AmenityResource($this->whenLoaded('amenity')),
            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
