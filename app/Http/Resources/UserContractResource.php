<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\UserContract **/
class UserContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'ip_address' => $this->ip_address,
            'terms_and_conditions' => $this->contract_terms_and_conditions,

            'is_current' => $this->when(isset($this->is_current), $this->is_current),

            'starting_at' => $this->starting_on->format('Y-m-d'),
            'ending_at' => $this->ending_on?->format('Y-m-d'),

            'accepted_at' => $this->accepted_on?->toDateTimeString(),
            'accepted' => $this->accepted,
            'sent_at' => $this->sent_on?->toDateTimeString(),

            'file' => $this->file_url,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
        ];
    }
}
