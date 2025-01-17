<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\BodyWeight **/
class BodyWeightResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'weight' => $this->weight,
            'recorded_at' => $this->recorded_on->toDateTimeString(),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
