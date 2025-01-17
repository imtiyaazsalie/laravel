<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DropInPackage\CreateDropInPackageRequest;
use App\Http\Requests\DropInPackage\ListDropInPackageClassesRequest;
use App\Http\Requests\DropInPackage\ListDropInPackagesRequest;
use App\Http\Requests\DropInPackage\ReadDropInPackageRequest;
use App\Http\Requests\DropInPackage\ReadDropInPackageSettingsRequest;
use App\Http\Requests\DropInPackage\ToggleDropInPackageStatusRequest;
use App\Http\Requests\DropInPackage\UpdateDropInPackageClassesRequest;
use App\Http\Requests\DropInPackage\UpdateDropInPackageRequest;
use App\Http\Requests\DropInPackage\UpdateDropInPackageSettingsRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\DropInPackageResource;
use App\Models\DropInPackage;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DropInPackageController extends Controller
{
    public function store(CreateDropInPackageRequest $request)
    {
        $dropInpackage = new DropInPackage();
        $dropInpackage->fill($request->validated());
        $dropInpackage->save();

        return new DropInPackageResource($dropInpackage);
    }

    #[QueryParam('filter[tenant_id]', 'int', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    public function list(ListDropInPackagesRequest $request)
    {
        $dropInBoxPackages = QueryBuilder::for(DropInPackage::class)
            ->allowedFilters([
                AllowedFilter::exact('is_active', 'is_active'),
            ])
            ->_paginate();

        return DropInPackageResource::collection($dropInBoxPackages);
    }

    public function getSettings(ReadDropInPackageSettingsRequest $request)
    {
        $tenantId = $request->input('filter.tenant_id');

        $tenant = Tenant::findOrFail($tenantId);

        return response()->json(
            Arr::get($tenant->extra_parameters, 'dropInPackageSettings')
        );
    }

    public function updateSettings(UpdateDropInPackageSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail($request->input('tenant_id'));

        $tenant->update(
            ['extra_parameters' => [
                'dropInPackageSettings' => [
                    'showAllClasses' => $request->get('show_all_classes'),
                    'paymentType' => $request->get('payment_type'),
                ],
            ]]
        );

        return response()->json(
            Arr::get($tenant->extra_parameters, 'dropInPackageSettings')
        );
    }

    public function show(ReadDropInPackageRequest $request, DropInPackage $dropInPackage)
    {
        return new DropInPackageResource($dropInPackage);
    }

    public function update(DropInPackage $dropInPackage, UpdateDropInPackageRequest $request)
    {
        $dropInPackage->update($request->all());

        return new DropInPackageResource($dropInPackage);
    }

    public function toggleStatus(ToggleDropInPackageStatusRequest $request, DropInPackage $dropInPackage)
    {
        $dropInPackage->toggleStatus();

        return new DropInPackageResource($dropInPackage);
    }

    public function getDropInClasses(ListDropInPackageClassesRequest $request, DropInPackage $dropInPackage)
    {
        return ClassResource::collection($dropInPackage->classes->filter(function ($class) use ($request) {
            return $class->location_id == $request->location_id;
        }));
    }

    public function updateDropInClasses(DropInPackage $dropInPackage, UpdateDropInPackageClassesRequest $request)
    {
        $classIds = $request->get('class_ids');
        $dropInPackage->classes()->where('box_facility_id', '=', $request->get('location_id'))->detach();

        foreach ($classIds as $classId) {
            $dropInPackage->classes()->where('box_facility_id', '=', $request->get('location_id'))->attach($classId);
        }

        return ClassResource::collection($dropInPackage->classes);
    }
}
