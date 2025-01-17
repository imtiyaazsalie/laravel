<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Classes;
use App\Models\ClassPackage;
use Illuminate\Http\Request;

/** @mixin Classes * */
class ClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        if (str($request->input('internalAppend'))->contains('withoutTenant')) {
            $this->unsetRelation('tenant');
        }

        if (str($request->input('internalAppend'))->contains('withoutLocations')) {
            $this->unsetRelation('location');
        }

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->toArray(),

            'start_time' => $this->start_time->toDateTimeString(),
            'end_time' => $this->end_time->toDateTimeString(),

            'recurring_start_date' => $this->classDates()->first()?->date->toDateString(),
            'recurring_end_date' => $this->recurring_end_date?->toDateString(),

            'is_virtual' => (int) $this->isVirtual(),
            'is_session' => (int) $this->isSession(),
            'is_free' => (int) $this->isFree(),
            'is_active' => (int) $this->isActive(),
            'is_visible_in_app' => (int) $this->isVisibleInApp(),
            'is_display_instructor_name' => (int) $this->isDisplayCoachName(),

            'limit' => $this->limit,
            'booking_threshold' => $this->booking_threshold,
            'cancellation_threshold' => $this->cancellation_threshold,

            'min_booked_members_count' => $this->min_booked_members_count,
            'auto_cancel_threshold_min' => $this->auto_cancel_threshold_min,

            'meeting_url' => $this->meeting_url,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),

            'bookings' => ClassBookingResource::collection($this->whenLoaded('bookings')),
            'dates' => ClassDateResource::collection($this->whenLoaded('classDates')),

            'packages' => PackageResource::collection($this->whenLoaded('classPackages', function () {
                return $this->classPackages->filter(function (ClassPackage $classPackage) {
                    return $classPackage->is_active && $classPackage->package->is_active;
                })->map(function (ClassPackage $classPackage) {
                    return $classPackage->package;
                });
            })),

            'recurring_days' => $this->whenLoaded('daysOfWeek', function () {
                return $this->getActiveClassDays()->pluck('day_id');
            }),

            'tags' => TagResource::collection($this->whenLoaded('tags')),

            'instructor_id' => $this->instructor_id,
            'instructor' => UserResource::make($this->headCoach?->user),

            'supporting_instructor_id' => $this->supporting_instructor_id,
            'supporting_instructor' => UserResource::make($this->supportingCoach?->user),
        ];
    }
}
