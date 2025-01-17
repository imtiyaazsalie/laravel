<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\UserOnHold **/
class UserOnHoldResource extends JsonResource
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

            'note' => $this->note,
            'pro_rata_fee' => $this->pro_rata_fee,
            'starts_at' => $this->start_date?->toDateString(),
            'release_at' => $this->release_date?->toDateString(),
            'is_extend_package' => $this->extend_package_end_date,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified?->toDateTimeString(),
        ];
    }
}
