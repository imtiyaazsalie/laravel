<?php

namespace App\Http\Resources;

use App\Enums\ClassBookingStatus;
use App\Helpers\JsonResource;
use App\Models\ClassDate;
use App\Models\LeadMember;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** @mixin ClassDate * */
class ClassDateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $tags = [];
        $classBookings = [];

        if ($this->relationLoaded('tags')) {
            $tags = $this->tags->isNotEmpty() ? $this->tags : $this->class->tags;
        }

        if ($this->relationLoaded('class')) {
            $this->class->setAttribute('start_time', Carbon::parse(strtotime($this->startTime()))->setDateFrom($this->class_date));
            $this->class->setAttribute('end_time', Carbon::parse(strtotime($this->endTime()))->setDateFrom($this->class_date));
            $this->class->setAttribute('min_booked_members_count', $this->min_booked_members_count);
            $this->class->setAttribute('auto_cancel_threshold_min', $this->auto_cancel_threshold_min);
        }

        if (! str($request->input('internalAppend'))->contains('withoutBookings')) {
            if (auth()->check()) {
                $classBookings = $this->classBookings()->whereIn('class_booking_status_id', [ClassBookingStatus::BOOKED, ClassBookingStatus::NO_SHOW])->get();
            } elseif ($request->has('filter.class_bookings_for_lead_token')) {
                $leadMember = LeadMember::find($request->input('filter.class_bookings_for_lead_token'));

                if ($leadMember) {
                    $classBookings = $this->classBookings()
                        ->whereIn('class_booking_status_id', [ClassBookingStatus::BOOKED, ClassBookingStatus::NO_SHOW])
                        ->where('user_id', $leadMember->user_id)
                        ->get();
                }
            }
        }

        return [
            'id' => $this->getKey(),

            'date' => $this->class_date->format('Y-m-d'),
            'name' => $this->name(),
            'description' => $this->description(),

            'start_time' => $this->startTime(),
            'end_time' => $this->endTime(),
            'limit' => $this->attendanceLimit(),
            'meeting_url' => $this->meetingUrl(),

            'booking_threshold_time' => $this->bookingThreshold()->format('H:i:s'),
            'booking_threshold_date' => $this->bookingThreshold()->format('Y-m-d'),

            'cancellation_threshold_time' => $this->cancellationThreshold()->format('H:i:s'),
            'cancellation_threshold_date' => $this->cancellationThreshold()->format('Y-m-d'),

            'min_booked_members_count' => $this->min_booked_members_count,
            'auto_cancel_threshold_min' => $this->auto_cancel_threshold_min,

            'is_active' => $this->is_active,
            'is_display_bookings' => $this->class->location->view_class_bookings,
            'is_display_booking_details' => $this->class->location->display_booking_details,

            'class_id' => $this->class_id,
            'class' => new ClassResource($this->whenLoaded('class')),

            'instructor_id' => $this->instructor_id,
            'instructor' => new UserResource($this->whenLoaded('headCoach')),

            'supporting_instructor_id' => $this->supporting_instructor_id,
            'supporting_instructor' => new UserResource($this->whenLoaded('supportingCoach')),

            'bookings_count' => $this->when(isset($this->attendanceCount), $this->attendanceCount),
            'bookings' => $this->when(auth()->check() || $request->has('filter.class_bookings_for_lead_token'), ClassBookingResource::collection($classBookings)),

            'waiting_list_count' => $this->when(isset($this->waitingCount), $this->waitingCount),
            'waiting_list' => $this->when(auth()->check() || $request->has('filter.class_bookings_for_lead_token'), ClassBookingWaitingResource::collection($this->whenLoaded('classBookingWaitingList'))),

            'tags' => TagResource::collection($tags),
        ];
    }
}
