<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Injury;
use Illuminate\Http\Request;

/** @mixin Injury */
class InjuryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'status' => $this->status,

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
            'deleted' => (int) $this->is_deleted,

            'updates' => InjuryUpdateResource::collection($this->whenLoaded('injuryUpdates')),
        ];
    }
}
