<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\ClassCoach **/
class CoachResource extends JsonResource
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

            'type' => $this->type->toArray(),
            'is_active' => $this->is_active,

            'user_id' => $this->coach_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
        ];
    }
}
