<?php

namespace App\Http\Resources;

use App\Models\ClassBookingWaitingList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassBookingWaitingList **/
class ClassBookingWaitingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'status' => $this->status->toArray(),

            'class_id' => $this->class_id,
            'class' => new ClassResource($this->whenLoaded('class')),

            'class_date_id' => $this->class_to_date_id,
            'class_date' => new ClassDateResource($this->whenLoaded('classDate')),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'user_package_id' => $this->user_package_id,
            'user_package' => new PackageResource($this->whenLoaded('userPackage')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserMinimalResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserMinimalResource($this->whenLoaded('updatedBy')),

            'created_at' => $this->dt_added->toDateTimeString(),
        ];
    }
}
