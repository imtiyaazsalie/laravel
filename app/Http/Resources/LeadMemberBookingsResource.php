<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\ClassBooking **/
class LeadMemberBookingsResource extends JsonResource
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
            'class_id' => $this->class_id,
            'class' => new ClassResource($this->whenLoaded('class')),

            'class_date_id' => $this->class_to_date_id,
            'class_date' => new ClassDateResource($this->whenLoaded('class_date')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
        ];
    }
}
