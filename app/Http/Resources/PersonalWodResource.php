<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\PersonalWod **/
class PersonalWodResource extends JsonResource
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

            'name' => $this->name,
            'description' => $this->description,
            'date' => $this->date?->toDateString(),
            'score' => $this->score,
            'note' => $this->note,

            'is_rx' => $this->is_rx,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => $this->when(! is_null($this->user_id) && ! is_null($this->tenant_id), new UserTenantResource($this->whenLoaded('userTenant'))),

            'measuring_unit_id' => $this->measuring_unit_id,
            'measuring_unit' => new MeasurementUnitResource($this->whenLoaded('measurementUnit')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
