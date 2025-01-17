<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

/** @mixin Tenant * */
class TenantResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'status_id' => $this->status->value,
            'status' => $this->status->toArray(),
            'name' => $this->name,
            'description' => $this->description,

            'website_url' => $this->website_url,
            'instagram_url' => $this->instagram_url,
            'facebook_url' => $this->facebook_url,
            'head_coaches' => UserResource::collection($this->whenLoaded('headCoaches')),

            'created_at' => $this->dt_added->toDateTimeString(),
            'updated_at' => $this->dt_modified->toDateTimeString(),

            'is_trial' => $this->is_trial,
            'is_sign_up_use_contracts_and_waivers' => $this->signup_use_contract_and_waivers,
            'sign_up_required_fields' => $this->sign_up_required_fields,
            'contract_terms_and_conditions' => $this->when($this->signup_use_contract_and_waivers && $request->has('include_contract_terms_and_conditions'), $this->contract_terms_and_conditions),
            'waiver_terms_and_conditions' => $this->when($this->leadSettings?->waiver && $request->has('include_digital_terms_and_conditions'), $this->leadSettings?->waiver?->digital_terms_and_conditions),

            'workout_threshold' => $this->workout_threshhold ?? 0,
            'sign_up_payment_options' => $this->signup_payment_options,
            'sign_up_debit_day_options' => $this->signup_debit_day_options,

            'deactivated_at' => $this->deactivated_on?->toDateTimeString(),

            'members_count' => $this->whenCounted('users'),
            'non_deactivated_members_count' => $this->whenCounted('nonDeactivatedUsers'),

            'settings' => new TenantSettingResource($this->whenLoaded('settings')),

            'region_id' => $this->region_id,
            'region' => new RegionResource($this->whenLoaded('region')),

            'tenant_billing_currency_id' => $this->tenant_billing_currency_id,
            'tenant_billing_currency' => new CurrencyResource($this->whenLoaded('tenantCurrency')),

            'member_billing_currency_id' => $this->member_billing_currency_id,
            'member_billing_currency' => new CurrencyResource($this->whenLoaded('memberCurrency')),

            'timezone_id' => $this->timezone_id,
            'timezone' => new TimezoneResource($this->whenLoaded('timezone')),

            'tenant_user' => new TenantUserResource($this->whenPivotLoadedAs('tenantUser', 'user_to_box', $this->tenantUser)),

            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'programmes' => ProgrammeResource::collection($this->whenLoaded('programmesActive')),
            'affiliations' => AffiliateResource::collection($this->whenLoaded('affiliations')),
        ];
    }
}
