<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Programme;

/** @mixin Programme **/
class ProgrammeResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,

            'is_active' => $this->is_active,
            // 'is_checked' => $this->programmeVisibility?->isEmpty() || $this->programmeVisibility?->contains($this->getKey()),

            $this->mergeWhen(isset($this->is_checked), [
                'is_checked' => $this->is_checked,
            ]),

            'affiliate' => $this->affiliate?->toArray(),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'parent_id' => $this->parent_id,
            'parent' => new ProgrammeResource($this->whenLoaded('parent')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'created_at' => $this->created_on->toDateTimeString(),
            'updated_at' => $this->updated_on->toDateTimeString(),
        ];
    }
}
