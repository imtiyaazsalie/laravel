<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ShowByPublicTokenRequest;
use App\Http\Requests\Tenants\TenantBulkAssignCategoryRequest;
use App\Http\Requests\Tenants\TenantCreateRequest;
use App\Http\Requests\Tenants\TenantListRequest;
use App\Http\Requests\Tenants\TenantShowRequest;
use App\Http\Requests\Tenants\TenantUpdateRequest;
use App\Http\Resources\TenantResource;
use App\Models\Location;
use App\Models\Tenant;
use App\Services\TenantService;

class TenantController extends Controller
{
    public function __construct(protected TenantService $tenantService)
    {
    }

    public function list(TenantListRequest $request)
    {
        return TenantResource::collection($this->tenantService->list());
    }

    public function show(TenantShowRequest $request, Tenant $tenant)
    {
        return new TenantResource($tenant);
    }

    public function create(TenantCreateRequest $request)
    {
        return new TenantResource($this->tenantService->store($request));
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant)
    {
        return new TenantResource($this->tenantService->update($request, $tenant));
    }

    public function bulkAssignCategory(TenantBulkAssignCategoryRequest $request)
    {
        foreach ($request->input('tenant_ids') as $id) {
            $box = Tenant::query()->find($id);

            if (! $box instanceof Tenant) {
                continue;
            }

            /** @var Location $boxFacility */
            foreach ($box->locations as $boxFacility) {
                $boxFacility->update(['category_id' => $request->input('location_category_id')]);
            }
        }

        return response()->noContent();
    }

    public function getByPublicToken(ShowByPublicTokenRequest $request, string $token)
    {
        $location = Location::query()
            ->with('tenant')
            ->where('public_token', $token)
            ->first();

        $tenant = $location
            ? $location->tenant
            : Tenant::query()->where('public_token', $token)->first();

        abort_if(! $tenant, 404, 'Public token not found.');

        $tenant = $tenant->load([
            'memberCurrency', 'settings', 'locations.paymentGateway', 'region',
            'leadSettings.waiver', 'programmesActive',
        ]);

        if ($location) {
            $tenant->setRelation('locations', collect([
                $location->withoutRelations(),
            ]));
        }

        return (new TenantResource($tenant))->additional([
            'is_location_specific' => (bool) $location,
        ]);
    }
}
