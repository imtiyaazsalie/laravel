<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

class OperatingHourResource extends JsonResource
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

            'day' => $this->day->name,

            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
