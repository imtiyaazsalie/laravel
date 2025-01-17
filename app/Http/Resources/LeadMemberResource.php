<?php

namespace App\Http\Resources;

use App\Enums\ClassBookingStatus;
use App\Helpers\JsonResource;
use App\Models\LeadMember;
use Illuminate\Http\Request;

/** @mixin LeadMember **/
class LeadMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->member_id,
            'user' => new UserResource($this->user),
            'status' => $this->status,
            'type' => $this->type,
            'source' => $this->source,
            'notes' => $this->notes,
            'last_contacted_at' => $this->last_contacted_date,
            'next_follow_up_at' => $this->next_follow_up_date,
            'converted_at' => $this->converted_on,
            'location_id' => $this->location_id,
            'location' => new LocationResource($this->whenLoaded('location')),
            'captured_by_id' => $this->captured_by_id,
            'captured_by' => new UserMinimalResource($this->whenLoaded('capturedBy')),
            'referred_by_id' => $this->referred_by_id,
            'referred_by' => new UserMinimalResource($this->whenLoaded('referredBy')),
            'waiver_id' => $this->waiver_id,
            'waiver' => new WaiverResource($this->whenLoaded('waiver')),
            'bookingsCount' => $this->whenLoaded('classBookings', function () {
                return $this->classBookings->where('class_booking_status_id', '=', ClassBookingStatus::BOOKED)->count();
            }),
            'redirect_url' => $this->redirect_url,
            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDateTimeString(),
            'deleted' => $this->deleted,

        ];
    }
}
