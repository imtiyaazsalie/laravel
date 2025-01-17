<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Amenity **/
class AmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
            // 'location_amenity' => $this->whenPivotLoadedAs('amenity_location', $this->locat),
        ];
    }
}
