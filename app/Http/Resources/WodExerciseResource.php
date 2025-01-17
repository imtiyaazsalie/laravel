<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\WodExercise;
use Illuminate\Http\Request;

/** @mixin WodExercise **/
class WodExerciseResource extends JsonResource
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

            'is_active' => $this->is_active,
            'order' => $this->wte_order,

            'wod_id' => $this->wod_id,
            'wod' => new WodResource($this->whenLoaded('wod')),

            'exercise_id' => $this->exercise_id,
            'exercise' => new ExerciseResource($this->exercise),

            'prefix' => new WodExercisePrefixResource($this->when(
                $this->relationLoaded('prefixes'),
                function () {
                    return $this->prefixes->where('is_active', 1)->last();
                }
            )),

            'created_at' => $this->dt_added->toDateTimeString(),

        ];
    }
}
