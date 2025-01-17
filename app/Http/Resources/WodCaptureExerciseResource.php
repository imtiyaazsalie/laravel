<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\WodCaptureExercise **/
class WodCaptureExerciseResource extends JsonResource
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

            'score' => $this->score,
            'note' => $this->note,

            'is_personal_best' => $this->is_personal_best,
            'is_rx' => $this->is_rx,
            'is_verified' => $this->is_verified,

            'wod_capture_id' => $this->wod_capture_id,
            'wod_capture' => new WodCaptureResource($this->capture->loadMissing(['user'])),

            'exercise_id' => $this->exercise_id,
            'exercise' => new ExerciseResource($this->whenLoaded('exercise')),
        ];
    }
}
