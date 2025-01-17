<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassType;
use App\Http\Controllers\Controller;
use App\Http\RequestFilters\TagsFilter;
use App\Http\Requests\Packages\CreatePackageRequest;
use App\Http\Requests\Packages\GetActiveLocationsRequest;
use App\Http\Requests\Packages\GetPackageClassesRequest;
use App\Http\Requests\Packages\GetProgrammesRequest;
use App\Http\Requests\Packages\ListPackageRequest;
use App\Http\Requests\Packages\PackageActionsRequest;
use App\Http\Requests\Packages\ReadPackageRequest;
use App\Http\Requests\Packages\UpdateActiveLocationsRequest;
use App\Http\Requests\Packages\UpdatePackageClassesRequest;
use App\Http\Requests\Packages\UpdatePackageRequest;
use App\Http\Requests\Packages\UpdateProgrammesRequest;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PackageResource;
use App\Http\Resources\ProgrammeResource;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\Location;
use App\Models\LocationPackage;
use App\Models\Package;
use App\Models\Programme;
use App\Models\ProgrammePackageVisibility;
use App\Services\ClassPackageService;
use App\Services\ClassService;
use App\Services\PackageService;
use App\Services\TagsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class PackageController extends Controller
{
    public function __construct(public PackageService $packageService, public TagsService $tagsService)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[package_id]', 'integer', required: false)]
    #[QueryParam('filter[type_id]', 'integer', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[is_for_sign_up]', 'boolean', required: false)]
    #[QueryParam('filter[is_for_buy_packages]', 'boolean', required: false)]
    #[QueryParam('filter[tag_ids]', 'integer', required: false)]
    public function list(ListPackageRequest $request): AnonymousResourceCollection
    {
        $tenantId = $request->input('filter.location_id') ? Location::find($request->input('filter.location_id'))->tenant_id : null;

        return PackageResource::collection(
            QueryBuilder::for(Package::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id')->default($tenantId),
                    AllowedFilter::callback('location_id', function (Builder $query, $value) {
                        $query->leftJoin('package_to_box_facility', 'packages.package_id', 'package_to_box_facility.package_id')
                            ->where(function (Builder $query) use ($value) {
                                $query->where('package_to_box_facility.box_facility_id', '=', $value)
                                    ->orWhereNull('package_to_box_facility.box_facility_id');
                            });
                    }),
                    AllowedFilter::callback('has_health_provider_price', function (Builder $query, $value) {
                        if ($value == 1) {
                            $query->whereNotNull('health_provider_price');
                        } else {
                            $query->whereNull('health_provider_price');
                        }
                    }),
                    AllowedFilter::exact('package_id'),
                    AllowedFilter::exact('type_id', 'package_limit_type_id'),
                    AllowedFilter::exact('is_active'),
                    AllowedFilter::exact('is_hidden'),
                    AllowedFilter::exact('is_for_sign_up', 'is_displayed'),
                    AllowedFilter::exact('is_for_buy_packages', 'is_display_on_buy_packages'),
                    AllowedFilter::custom('tag_ids', new TagsFilter((new Package())->getTable(), 'package')),
                ])
                ->allowedSorts([
                    AllowedSort::field('priority', 'priority')->defaultDirection(SortDirection::DESCENDING),
                    AllowedSort::field('name', 'name'),
                ])
                ->allowedIncludes('locations')
                ->select('packages.*')
                ->addSelect(DB::raw('CASE WHEN priority IS NULL THEN 1 ELSE 0 END as priority_is_set'))
                ->defaultSort('priority_is_set', 'priority', 'package_name')
                ->with('tags')
                ->_paginate());
    }

    public function actions(PackageActionsRequest $request)
    {
        foreach ($request->input('package_ids') as $packageId) {

            $package = Package::find($packageId);

            $package->update([
                'late_cancellation_fee' => $request->has('late_cancellation_fee') ? $request->input('late_cancellation_fee') : $package->late_cancellation_fee,
                'no_show_fee' => $request->has('no_show_fee') ? $request->input('no_show_fee') : $package->no_show_fee,
            ]);
        }

        return response()->noContent();
    }

    public function show(Package $package, ReadPackageRequest $request): PackageResource
    {
        return new PackageResource($package);
    }

    public function store(CreatePackageRequest $request): PackageResource
    {
        $defaultPeriodInterval = $request->has('default_period')
            ? 'P'.$request->input('default_period').Str::upper(Str::substr($request->input('default_period_interval'), 0, 1))
            : null;

        $package = $this->packageService->store(
            array_merge(
                $request->only([
                    'tenant_id',
                    'name',
                    'description',
                    'limit',
                    'type',
                    'late_cancellation_fee',
                    'no_show_fee',
                    'price',
                    'topup_price',
                    'health_provider_price',
                    'is_hidden',
                    'is_displayed',
                    'is_display_on_buy_packages',
                    'priority',
                ]),
                [
                    'default_period_interval' => $defaultPeriodInterval,
                ]
            )
        );

        if ($request->tag_ids) {
            $this->tagsService->sync($request->tag_ids, $package, auth()->user()->getAuthIdentifier());
        }

        return new PackageResource($package->loadMissing('tags'));
    }

    public function update(Package $package, UpdatePackageRequest $request): PackageResource
    {
        $defaultPeriodInterval = 'P'.$request->get('default_period').Str::upper(Str::substr($request->get('default_period_interval'), 0, 1));

        $package->update(
            array_merge(
                $request->safe()->only([
                    'name',
                    'description',
                    'limit',
                    'type',
                    'price',
                    'late_cancellation_fee',
                    'no_show_fee',
                    'health_provider_price',
                    'topup_price',
                    'is_hidden',
                    'is_displayed',
                    'is_display_on_buy_packages',
                    'priority',
                    'is_active',
                ]),
                [
                    'default_period_interval' => $defaultPeriodInterval,
                ]
            )
        );

        if ($request->has('tag_ids')) {
            $this->tagsService->sync($request->tag_ids, $package, auth()->user()->getAuthIdentifier());
        }

        return new PackageResource($package->loadMissing('tags'));
    }

    public function getPackageClasses(Package $package, GetPackageClassesRequest $request): JsonResponse
    {
        $location = Location::query()->find($request->safe()->collect()->get('filter')['location_id']);

        $activeClassesByBoxFacility = (new ClassService())->getAllActiveClassesByBoxFacility($location);

        $classes = (new PackageService())->getClassLookupForPackageAndFacility($package, $location);

        $data = [];

        /** @var Classes $class */
        foreach ($activeClassesByBoxFacility as $class) {

            $activeDays = null;

            // remove onceOff classes that have passed
            if ($class->class_type_id === ClassType::ONCE_OFF) {
                // Find the classToDate for this class

                $classToDate = ClassDate::query()
                    ->where('class_id', '=', $class->getKey())
                    ->where('is_active', '=', 1)
                    ->first();

                if (! $classToDate instanceof ClassDate) {
                    continue;
                }

                $today = today();

                // Check that the date has passed already
                if ($classToDate->class_date->lessThan($today)) {
                    continue;
                }
            } else {
                foreach ($class->daysOfWeek()->where('class_to_days.is_active', true)->get() as $classDay) {
                    $activeDays[] = $classDay?->day_id;
                }
            }

            $data[] = [
                'class' => [
                    'id' => $class->getKey(),
                    'name' => $class->class_name,
                    'type' => $class->class_type_id,
                    'start_time' => $class->start_time->format('H:i:s'),
                    'end_time' => $class->end_time->format('H:i:s'),
                    'active_days' => $activeDays,
                ],
                'is_checked' => $classes->pluck('id')->contains($class->getKey()),
            ];
        }

        return response()->json($data);
    }

    public function updatePackageClasses(Package $package, UpdatePackageClassesRequest $request): Response
    {
        $boxFacility = Location::query()->find($request->safe()->collect()->get('location_id'));
        $classIds = $request->safe()->collect()->get('class_ids');

        $classPackages = (new ClassPackageService())->getClassPackagesByPackageAndBoxFacility($package, $boxFacility);

        // Remove all the current class packages for this package and boxFacility
        foreach ($classPackages as $classPackage) {
            $classPackage->delete();
        }

        foreach ($classIds as $classId) {
            $class = Classes::query()->find($classId);

            if (! $class instanceof Classes) {
                continue;
            }

            $classPackage = ClassPackage::query()->create([
                'package_id' => $package->getKey(),
                'class_id' => $class->getKey(),
            ]);
        }

        return response()->noContent();
    }

    public function getProgrammes(GetProgrammesRequest $request, Package $package)
    {
        $tenant = $package->tenant;

        return ProgrammeResource::collection((new PackageService())->getProgrammesForPackage($package, $tenant));
    }

    public function updateProgrammes(UpdateProgrammesRequest $request, Package $package): Response
    {
        $programmeIds = $request->safe()->collect()->get('programme_ids');

        $package->load('programmeVisibility');

        // first remove programmes that were there before, but are not included now
        foreach ($package->programmeVisibility as $previousProgramme) {
            if (! in_array($previousProgramme->programme_id, $programmeIds)) {
                $previousProgramme->delete();
            }
        }

        $package->load('programmeVisibility');

        // Loop through all the $programmeIds
        foreach ($programmeIds as $id) {
            $programme = Programme::query()->find($id);

            if (! $programme instanceof Programme || $programme->tenant_id != $package->tenant_id) {
                continue;
            }

            // Check programme is contained inside programmesWithVisibility
            if ($package->programmeVisibility->pluck('programme_id')->contains($id)) {
                continue;
            }

            // Add the new programme to the package
            ProgrammePackageVisibility::query()->create([
                'programme_id' => $programme->getKey(),
                'package_id' => $package->getKey(),
            ]);
        }

        return response()->noContent();
    }

    public function getActiveLocations(GetActiveLocationsRequest $request, Package $package)
    {
        return LocationResource::collection((new PackageService())->getLocationsForPackage($package));
    }

    public function updateActiveLocations(UpdateActiveLocationsRequest $request, Package $package): Response
    {
        // first remove box facilities that were there before, but are not included now
        foreach ($package->locations as $previousBoxFacility) {

            if (! in_array($previousBoxFacility->getKey(), $request->safe()->collect()->get('location_ids'))) {

                LocationPackage::query()
                    ->where('package_id', '=', $package->getKey())
                    ->where('box_facility_id', '=', $previousBoxFacility->getKey())
                    ->delete();
            }
        }

        // Loop through all the $boxFacilityIds
        foreach ($request->safe()->collect()->get('location_ids') as $boxFacilityId) {
            $boxFacility = Location::query()->find($boxFacilityId);

            if (! $boxFacility instanceof Location) {
                continue;
            }

            // Check box facility is contained inside packages box facilities
            if ($package->locations->contains($boxFacility)) {
                continue;
            }

            // Add the new box facility to the package
            LocationPackage::query()->create([
                'package_id' => $package->getKey(),
                'box_facility_id' => $boxFacility->getKey(),
            ]);
        }

        return response()->noContent();
    }
}
