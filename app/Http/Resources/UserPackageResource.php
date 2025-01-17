<?php

namespace App\Http\Resources;

use App\Enums\PackageType;
use App\Helpers\JsonResource;
use App\Models\Location;
use App\Models\UserPackage;
use App\Services\ClassService;
use App\Services\TenantUserService;
use Illuminate\Http\Request;

/** @mixin UserPackage * */
class UserPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $package = $this->package;
        $location = (new TenantUserService())->getLocationUserByTenant($this->user_id, $package->tenant)?->location;

        if (! str($request->input('internalAppend'))->contains('withoutLocations')) {
            if ($this->locations->isNotEmpty()) {

                $this->setRelation('locations', $this->locations->map(function ($item) {
                    return $item->location;
                }));

            } else {
                $this->setRelation('locations', $this->tenant->locations);
            }
        }

        if (str($request->input('internalAppend'))->contains('withoutTenant')) {
            $this->unsetRelation('tenant');
        }

        return [
            'id' => $this->getKey(),
            'start_date' => $this->effective_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->isActive(),

            'sessions_available' => $this->package->type == PackageType::DROP_IN ? $this->sessions_available : ($location instanceof Location ? (new ClassService())->getSessionsRemainingForUserPackage($this->resource, $location) : null),
            'sessions_available_text' => $this->package->type == PackageType::DROP_IN ? '' : ($location instanceof Location ? (new ClassService())->getSessionsRemainingForUserPackageForDisplay($this->resource, $location) : null),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'locations' => LocationResource::collection($this->whenLoaded('locations')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'package_id' => $this->package_id,
            'package' => new PackageResource($this->whenLoaded('package')),

            'invoices' => UserInvoiceResource::collection($this->whenLoaded('invoices')),

            'class_booking' => $this->when(isset($this->class_booking), new ClassBookingResource($this->class_booking)),

            'deleted' => $this->deleted ?? 0,
        ];
    }
}
