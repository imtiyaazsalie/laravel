<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Http\Resources\StripeConnect\PaymentMethods\PaymentMethodResource;
use App\Models\TenantUser;
use App\Services\TenantUserService;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonSerializable;

/** @mixin TenantUser * */
class TenantUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        $financeDetails = null;

        if ((str($request->input('append'))->contains('active_user_packages'))) {
            $this->append('activeUserPackages');

            $this->activeUserPackages->transform(
                fn ($up) => $up->withoutRelations('userTenant')
            );
        }

        if ((str($request->input('append'))->contains('finance_details'))) {
            $financeDetails = (new TenantUserService)->getFinanceDetailsForTenantUser($this->resource);
        }

        $mandate = null;

        if (! is_null($this->gocardless_mandate)) {
            $mandate = new MandateGoCardlessResource($this->gocardless_mandate);
        } elseif (! is_null($this->mandate)) {
            $mandate = new MandateResource($this->mandate);
        } elseif (! is_null($this->stripe_mandate)) {
            $mandate = $this->stripe_mandate;
        }

        return [
            'id' => $this->user_to_box_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'start_date' => $this->effective_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'type_id' => $this->type,
            'type' => $this->type?->toArray(),
            'debit_status_id' => $this->debit_status,
            'debit_status' => $this->debit_status?->toArray(),
            'status_id' => $this->status,
            'status' => $this->status?->toArray(),
            'programme_id' => $this->programme_id,
            'programme' => new ProgrammeResource($this->whenLoaded('programme')),
            'default_location_id' => $this->default_box_facility_id,
            'default_location' => new LocationResource($this->whenLoaded('defaultLocation')),
            'region_id' => $this->region_id,
            'region' => new RegionResource($this->whenLoaded('region')),
            'assigned_user_id' => $this->assignedCoach?->user_id,
            'assigned_user' => new UserMinimalResource($this->assignedCoach?->user),
            'access_privileges' => UserAccessPrivilegeResource::collection($this->whenLoaded('accessPrivileges')),
            'location_access_privileges' => LocationAccessPrivilegeResource::collection($this->whenLoaded('locationAccessPrivileges')),

            'lead_member_id' => $this->lead_member_id,
            'lead_member' => new LeadMemberResource($this->whenLoaded('leadMember')),

            'user_contract' => new UserContractResource($this->whenLoaded('userContract')),
            'waiver' => new WaiverResource($this->whenAppended('waiver')),
            'member_id' => $this->member_id,
            'bio' => $this->bio,
            'notes' => $this->notes,
            'is_high_risk' => $this->high_risk,
            'landing_screen' => $this->landing_screen,
            'joined_at' => $this->created_on?->toDateTimeString(),
            'activated_at' => $this->activated_on?->toDateTimeString(),
            'deactivated_at' => $this->deactivated_on?->toDateTimeString(),

            $this->mergeWhen($this->relationLoaded('locations'), [
                'locations' => LocationResource::collection(
                    $this->locations->filter(fn ($location) => $location->tenant_id === $this->tenant_id)
                ),
            ]),

            'last_attended_on' => $this->when($this->last_attended_on, $this->last_attended_on),
            'go_cardless_link_sent_on' => $this->go_cardless_link_sent_on?->toDateTimeString(),
            'payment_token_link_sent_on' => $this->payment_token_link_sent_on?->toDateTimeString(),
            'upfront_payment_end_date' => $this->upfront_payment_end_date?->toDateString(),
            'total_amount' => $this->whenAppended('totalAmount'),

            'user_packages' => UserPackageResource::collection($this->whenAppended('activeUserPackages')),

            'has_sessions_available_for_date' => $this->has_sessions_available_for_date,

            $this->mergeWhen(isset($this->is_overdue), [
                'is_overdue' => $this->is_overdue,
            ]),

            $this->mergeWhen(isset($financeDetails), [
                'finance_details' => $financeDetails,
            ]),

            'is_onboarded' => $this->when(! is_null($this->is_onboarded), $this->is_onboarded),
            'mandate' => $this->when(! is_null($mandate), $mandate),
            'payment_method' => new PaymentMethodResource($this->payment_method),

            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDateTimeString(),
            'deleted' => $this->deleted,
        ];
    }
}
