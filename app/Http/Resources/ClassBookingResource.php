<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\ClassBooking;
use App\Services\ClassService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** @mixin ClassBooking * */
class ClassBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $location = $this->class->location;
        $usersTimezone = new \DateTimeZone((new ClassService())->getTimezoneForBoxFacilityOrBox($location)->zone);

        if (str($request->input('internalAppend'))->contains('withoutClass')) {
            $this->unsetRelation('class');
        }

        return [
            'id' => $this->getKey(),

            'status_id' => $this->status,
            'status' => $this->status->toArray(),

            'is_top_up_used' => $this->top_up_used,

            'checked_in_at' => $this->checked_in_at instanceof Carbon ? $this->checked_in_at->setTimezone($usersTimezone)->toDateTimeString() : null,
            'checked_out_at' => $this->checked_out_at instanceof Carbon ? $this->checked_out_at->setTimezone($usersTimezone)->toDateTimeString() : null,

            $this->mergeWhen(! $this->user_id, new NonMemberResource($this)),

            $this->mergeWhen(isset($this->is_first_booking_at_location), [
                'is_first_booking_at_location' => $this->is_first_booking_at_location,
            ]),

            $this->mergeWhen(isset($this->is_user_overdue_at_location), [
                'is_user_overdue_at_location' => $this->is_user_overdue_at_location,
            ]),

            'class_id' => $this->class_id,
            'class' => new ClassResource($this->whenLoaded('class')),

            'class_date_id' => $this->class_to_date_id,
            'class_date' => new ClassDateResource($this->whenLoaded('classDate')),

            'lead_member_id' => $this->lead_member_id,
            'lead_member' => new LeadMemberResource($this->whenLoaded('leadMember')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'user_package_id' => $this->user_package_id,
            'user_package' => new UserPackageResource($this->whenLoaded('userPackage')),

            'coronavirus_questionaire_result' => new CoronavirusQuestionnaireResultResource($this->whenLoaded('coronavirusQuestionaireResult')),

            'created_by_id' => $this->created_by_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            'updated_by_id' => $this->updated_by_id,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),

            'created_at' => $this->dt_added instanceof Carbon ? $this->dt_added->setTimezone($usersTimezone)->toDateTimeString() : null,
            'updated_at' => $this->dt_modified instanceof Carbon ? $this->dt_modified->setTimezone($usersTimezone)->toDateTimeString() : null,
        ];
    }
}
