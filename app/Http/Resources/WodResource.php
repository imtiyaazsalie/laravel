<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\Wod **/
class WodResource extends JsonResource
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
            'nickname' => $this->nickname,
            'description' => $this->description,
            'date' => $this->date->toDateString(),
            'warm_up' => $this->warm_up,
            'cool_down' => $this->cool_down,
            'coach_notes' => $this->coach_notes,
            'member_notes' => $this->member_notes,

            'programme_id' => $this->programme_id,
            'programme' => new ProgrammeResource($this->whenLoaded('programme')),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'wod_exercises' => WodExerciseResource::collection($this->whenLoaded('exercises')),

            'created_at' => $this->dt_added->toDateTimeString(),
        ];
    }
}
