<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\Request;

/** @mixin Package * */
class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {

        if (str($request->input('internalAppend'))->contains('withoutTenant')) {
            $this->unsetRelation('tenant');
        }

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'late_cancellation_fee' => $this->late_cancellation_fee,
            'no_show_fee' => $this->no_show_fee,
            'health_provider_price' => $this->health_provider_price,
            'topup_price' => $this->topup_price,
            'type_id' => $this->type,
            'type' => $this->type?->toArray(),
            'limit' => $this->limit,
            'default_period' => (new PackageService())->getDefaultPeriod($this->default_period_interval),
            'default_period_type' => (new PackageService())->getDefaultPeriodType($this->default_period_interval),

            'is_active' => $this->is_active,
            'is_hidden' => $this->is_hidden,
            'is_display_on_sign_up' => $this->is_displayed,
            'is_display_on_buy_packages' => $this->is_display_on_buy_packages,

            'priority' => $this->priority,

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_package' => new UserPackageResource(
                $this->whenPivotLoadedAs('userPackage', 'user_to_package', $this->userPackage)
            ),

            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'programmes' => ProgrammeResource::collection($this->whenLoaded('programmes')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
