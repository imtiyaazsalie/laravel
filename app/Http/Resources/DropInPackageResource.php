<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\DropInPackage **/
class DropInPackageResource extends JsonResource
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
            'price' => $this->price,
            'priority' => $this->priority,

            'is_active' => $this->is_active,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'classes' => ClassResource::collection($this->whenLoaded('classes')),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
