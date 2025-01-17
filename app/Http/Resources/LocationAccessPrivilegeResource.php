<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\LocationAccessPrivilege;
use Illuminate\Http\Request;

/** @mixin LocationAccessPrivilege * */
class LocationAccessPrivilegeResource extends JsonResource
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
            'revoked' => $this->revoked,

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'location_id' => $this->box_facility_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'created_on' => $this->created_on->toDateTimeString(),
            'updated_on' => $this->updated_on->toDateTimeString(),
            'revoked_on' => $this->revoked_on?->toDateTimeString(),

        ];
    }
}
