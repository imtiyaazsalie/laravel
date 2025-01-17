<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\ExerciseCategory;
use Illuminate\Http\Request;

/** @mixin ExerciseCategory * */
class ExerciseCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'name' => $this->name,

            'is_benchmark' => $this->is_benchmark,
            'is_active' => $this->is_active,
        ];
    }
}
