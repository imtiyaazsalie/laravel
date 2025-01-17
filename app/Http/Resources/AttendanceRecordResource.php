<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\AttendanceRecord;
use App\Services\ClassService;
use Illuminate\Http\Request;

/** @mixin AttendanceRecord */
class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $boxFacility = $this->location ?: $this->classBooking?->class->location ?: $this->classDate?->class->location;
        $usersTimezone = new \DateTimeZone((new ClassService())->getTimezoneForBoxFacilityOrBox($boxFacility)->zone);

        return [
            'id' => $this->getKey(),

            $this->mergeWhen(! $this->user_id, ['non_member' => [
                'name' => $this->name,
                'surname' => $this->surname,
                'id_number' => $this->id_number,
                'date_of_birth' => $this->date_of_birth?->toDateString(),
            ]]),

            'checked_in_at' => $this->checked_in_at instanceof \DateTime ? $this->checked_in_at->setTimezone($usersTimezone)->format('Y-m-d H:i:s') : null,
            'checked_out_at' => $this->checked_out_at instanceof \DateTime ? $this->checked_out_at->setTimezone($usersTimezone)->format('Y-m-d H:i:s') : null,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'booking_id' => $this->class_booking_id,
            'booking' => new ClassBookingResource($this->whenLoaded('classBooking')),

            'class_date_id' => $this->class_to_date_id,
            'class_date' => new ClassDateResource($this->whenLoaded('classDate')),

            'health_provider_id' => $this->health_provider_id,
            'health_provider' => new HealthProviderResource($this->whenLoaded('healthProvider')),

            'created_on' => $this->created_on->toDateTimeString(),
            'updated_on' => $this->updated_on?->toDateTimeString(),
        ];
    }
}
