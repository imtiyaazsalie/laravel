<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\InjuryUpdate **/
class InjuryUpdateResource extends JsonResource
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
            'content' => $this->content,

            'injury_id' => $this->injury_id,
            'injury' => new InjuryResource($this->whenLoaded('injury')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->created_on->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
