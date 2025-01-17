<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LocationUser */
class UserLocationResource extends JsonResource
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

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'effective_date' => $this->effective_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
