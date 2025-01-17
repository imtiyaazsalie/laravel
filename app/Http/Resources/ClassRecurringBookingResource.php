<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\ClassRecurringBooking;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonSerializable;

/** @mixin ClassRecurringBooking **/
class ClassRecurringBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array|JsonSerializable|Arrayable
    {
        return [
            'id' => $this->getKey(),

            'is_active' => $this->active,
            'ends_at' => $this->dt_deactivate?->toDateTimeString(),

            'class_id' => $this->class_id,
            'class' => new ClassResource($this->whenLoaded('class')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'user_package_id' => $this->user_to_package_id,
            'user_package' => new UserPackageResource($this->whenLoaded('userPackage')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'days_of_the_week' => $this->whenLoaded('daysOfWeek', function () {
                return $this->daysOfWeek->pluck('class_day_id');
            }),

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified?->toDateTimeString(),
        ];
    }
}
