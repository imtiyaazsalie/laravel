<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\WodExercisePrefix **/
class WodExercisePrefixResource extends JsonResource
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

            'prefix' => $this->prefix,

            'is_active' => $this->is_active,

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),
        ];
    }
}
