<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\BodyMeasurements**/
class BodyMeasurementResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),

            'tricep' => $this->tricep,
            'subscapular' => $this->subscapular,
            'abdominal' => $this->abdominal,
            'suprailiac' => $this->suprailiac,
            'thigh' => $this->thigh,
            'calf' => $this->calf,
            'body_fat_percentage' => $this->body_fat_percentage,
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
