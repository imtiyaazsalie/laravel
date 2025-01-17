<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Exercise **/
class ExerciseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,
            'description' => $this->description,
            'resource_url' => $this->resource_url ?? null,
            'rx_male' => $this->rx_male,
            'rx_female' => $this->rx_female,

            'is_benchmark' => $this->is_benchmark,
            'is_active' => $this->is_active,
            'is_personal_best' => $this->is_pb,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'exercise_category_id' => $this->category,
            'exercise_category' => new ExerciseCategoryResource($this->whenLoaded('exerciseCategory')),

            'measuring_unit_id' => $this->measuring_unit_id,
            'measuring_unit' => new MeasurementUnitResource($this->measureUnit),

            'created_at' => $this->dt_added?->toDateString(),
            'updated_at' => $this->dt_modified?->toDateString(),

        ];
    }
}
