<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\CoachRate **/
class CoachRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),

            'strategy' => $this->strategy->toArray(),
            'type' => $this->type->toArray(),
            'amount' => $this->amount,
            'min_members' => $this->min_members,
            'max_members' => $this->max_members,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
