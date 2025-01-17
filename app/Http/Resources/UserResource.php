<?php

namespace App\Http\Resources;

use App\Enums\UserType;
use App\Helpers\JsonResource;
use App\Http\Resources\StripeConnect\PaymentMethods\PaymentMethodResource;
use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User * */
class UserResource extends JsonResource
{
    public function redacted(): bool
    {
        $requestUser = request()->user();

        if (! $requestUser && ! request()->passportClient) {
            return true;
        }

        if (request()->passportClient?->name === 'discovery') {
            return false;
        }

        return $this->is_redacted
            && $requestUser->getAuthIdentifier() !== $this->getAuthIdentifier()
            && ! $requestUser->isAdmin();
    }

    public function toArray(Request $request): array
    {
        if (request()->input('filter.is_mimimal_data')) {
            return (new UserMinimalResource($this))->toArray($request);
        }

        if (request()->passportClient?->name !== 'discovery' && (! auth()->check())) {
            return (new UserMinimalResource($this))->toArray($request);
        }

        $mandate = null;

        if (! is_null($this->gocardless_mandate)) {
            $mandate = new MandateGoCardlessResource($this->gocardless_mandate);
        } elseif (! is_null($this->mandate)) {
            $mandate = new MandateResource($this->mandate);
        } elseif (! is_null($this->stripe_mandate)) {
            $mandate = $this->stripe_mandate;
        }

        $redacted = $this->redacted();

        return [
            'id' => $this->getKey(),

            $this->mergeWhen(
                $redacted,
                [
                    'name' => $this->name,
                    'surname' => str($this->surname)->ucfirst()->charAt(0),
                    'email' => null,
                    'date_of_birth' => null,
                    'age' => null,
                    'id_number' => null,
                    'address' => null,
                ],
                [
                    'name' => $this->name,
                    'surname' => $this->surname,
                    'email' => str($this->email)->endsWith('@octiv.localhost')
                        ? $this->email_alt
                        : $this->email,
                    'date_of_birth' => $this->dob?->toDateString(),
                    'age' => $this->age(),
                    'id_number' => $this->id_number,
                    'address' => $this->address,
                ]
            ),

            'mobile' => $this->mobile,
            'type_id' => $this->user_type_id,
            'type' => UserType::tryFrom($this->user_type_id)?->toArray(),
            'gender' => $this->gender?->toArray(),
            'image' => $this->image_url,
            'medical_condition' => $this->medical_condition,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_mobile' => $this->emergency_contact_mobile,
            'health_provider_id' => $this->health_provider_id,
            'health_provider' => new HealthProviderResource($this->whenLoaded('healthProvider')),

            'user_tenants' => TenantUserResource::collection($this->whenLoaded('tenantUser')),
            'user_tenant' => new TenantUserResource($this->whenLoaded('userTenant')),

            'has_accepted_terms_and_conditions' => $this->hasAcceptedTermsAndConditions(),

            'injuries' => InjuryResource::collection($this->whenLoaded('injuries')),
            'is_injured' => $this->whenLoaded('injuries', $this->isInjured()),

            'is_onboarded' => $this->when(! is_null($this->is_onboarded), $this->is_onboarded),
            'mandate' => $this->when(! is_null($mandate), $mandate),
            'access_token' => $this->when(! is_null($this->access_token), $this->access_token),
            'payment_method' => new PaymentMethodResource($this->payment_method),

            'created_at' => $this->created_on?->toDateTimeString(),
            'updated_at' => $this->updated_on?->toDateTimeString(),
            'deleted' => $this->deleted,
            'is_redacted' => $this->is_redacted,
        ];
    }
}
