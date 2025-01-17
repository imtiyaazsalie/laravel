<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\CoronavirusQuestionnaireResult;
use Illuminate\Http\Request;

/** @mixin CoronavirusQuestionnaireResult * */
class CoronavirusQuestionnaireResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'temperature' => $this->temperature,
            'has_cough' => $this->has_cough,
            'has_difficulty_breathing' => $this->has_difficulty_breathing,
            'has_fever' => $this->has_fever,
            'has_been_in_contact_experiencing' => $this->has_been_in_contact_experiencing,
            'has_been_in_contact_positive' => $this->has_been_in_contact_positive,
            'has_travelled' => $this->has_travelled,
            'travelled_where' => $this->travelled_where,

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'class_booking_id' => $this->class_booking_id,
            'class_booking' => new ClassBookingResource($this->whenLoaded('classBooking')),
        ];
    }
}
