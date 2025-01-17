<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Programmes\CreateProgrammeRequest;
use App\Http\Requests\Programmes\ListProgrammesRequest;
use App\Http\Requests\Programmes\ToggleProgrammeStatusRequest;
use App\Http\Requests\Programmes\UpdateProgrammeRequest;
use App\Http\Resources\ProgrammeResource;
use App\Jobs\CopyGlobalWods;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\TenantAffiliation;
use App\Services\ProgrammeService;
use App\Services\TenantUserService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProgrammeController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[is_global]', 'boolean', required: false)]
    #[QueryParam('filter[package_id]', 'integer', required: false)]
    #[QueryParam('filter[affiliate_id]', 'integer', required: false)]
    public function list(ListProgrammesRequest $request): AnonymousResourceCollection
    {
        $tenant = Tenant::findOrFail($request->input('filter.tenant_id'));

        $programmes = QueryBuilder::for(Programme::class)
            ->allowedFilters([
                AllowedFilter::exact('affiliate_id'),
                AllowedFilter::callback('is_global', function ($query) {
                    $query->whereNotNull('parent_id');
                }),
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::scope('search'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedIncludes([
                'parent',
            ])
            ->orderBy('name')
            ->get();

        if (auth()->check()) {
            $user = $request->user();

            if ((new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant)?->isMember()) {
                $programmes = $programmes->filter(function (Programme $programme) use ($user) {
                    return (new ProgrammeService())->hasPackageWithProgrammeVisibility($programme, $user);
                });
            }
        }

        if ($request->input('filter.package_id')) {
            $package = Package::query()->findOrFail($request->input('filter.package_id'));

            if ($package instanceof Package) {
                $programmes = $programmes->filter(function ($programme) use ($package) {
                    return $package->programmeVisibility->isEmpty()
                        || $package->programmeVisibility->pluck('programme_id')->contains($programme->getKey());
                });
            }
        }

        return ProgrammeResource::collection($programmes->paginate());
    }

    public function store(CreateProgrammeRequest $request): ProgrammeResource
    {
        $programme = Programme::create([
            ...$request->validated(),
            'created_by_id' => auth()->user()->getAuthIdentifier(),
        ]);

        return new ProgrammeResource($programme);
    }

    public function update(UpdateProgrammeRequest $request, Programme $programme): ProgrammeResource
    {
        $programme->update($request->validated());

        return new ProgrammeResource($programme);
    }

    public function toggleStatus(ToggleProgrammeStatusRequest $request, Programme $programme): ProgrammeResource
    {
        abort_if(
            $programme->hasGlobalParent() && $programme->isNotActive() && TenantAffiliation::isNotAffiliate($programme->tenant_id, $programme->affiliate_id),
            Response::HTTP_FORBIDDEN,
            'You cannot activate a global affiliate programme unless you are an affiliate. Please contact support for assistance with this if required.'
        );

        $programme->toggleStatus();

        if ($programme->hasGlobalParent() && $programme->isActive()) {
            dispatch(
                new CopyGlobalWods($programme->parent, $programme)
            );
        }

        return new ProgrammeResource($programme);
    }
}
