<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\LeadWaivers;
use Illuminate\Http\Request;

/** @mixin LeadWaivers * */
class WaiverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'status' => $this->status,
            'ip_address' => $this->ip_address,
            'signed_at' => $this->signed_on?->toDateTimeString(),

            'is_digital' => $this->digital,
            'digital_terms_and_conditions' => $this->digital_terms_and_conditions,

            'file' => [
                'path' => $this->file_path,
                'mime' => $this->file_mime,
                'name' => $this->file_name,
            ],

            'parent_id' => $this->parent_id,
            'parent' => new WaiverResource($this->whenLoaded('parent')),

            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
