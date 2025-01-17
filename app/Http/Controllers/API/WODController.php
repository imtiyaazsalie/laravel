<?php

namespace App\Http\Controllers\API;

use App\Enums\Affiliate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wod\DeleteWodRequest;
use App\Http\Requests\Wod\ImportWodsRequest;
use App\Http\Requests\Wod\ListWodRequest;
use App\Http\Requests\Wod\ShowWodRequest;
use App\Http\Requests\Wod\StoreWodRequest;
use App\Http\Requests\Wod\UpdateWodRequest;
use App\Http\Resources\WodResource;
use App\Imports\WodImport;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\Wod;
use App\Services\ProgrammeService;
use App\Services\TenantUserService;
use App\Services\WODExerciseService;
use App\Services\WODService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WODController extends Controller
{
    public function __construct(
        private WODService $wod,
        private WODExerciseService $wodExercise
    ) {
        //
    }

    public function import(ImportWodsRequest $request)
    {
        HeadingRowFormatter::default('slug');

        $import = new WodImport(
            tenant: $request->tenant_id ? Tenant::findOrFail($request->tenant_id) : null,
            affiliate: $request->enum('affiliate_id', Affiliate::class)
        );

        Excel::import($import, $request->file('file'));

        return response()->noContent();
    }

    public function store(StoreWodRequest $request)
    {
        $wod = $this->wod->store($request->safe()->except(['exercises']));

        if ($request->has('exercises')) {
            foreach ($request->get('exercises') as $item) {
                $this->wodExercise->storeWodExercise($item, $wod);
            }
        }

        return new WodResource($wod);
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[starts_after]', 'string', required: false)]
    #[QueryParam('filter[ends_before]', 'string', required: false)]
    #[QueryParam('filter[use_workout_threshold]', 'integer', required: false)]
    #[QueryParam('filter[programme_id]', 'integer', required: false)]
    public function list(ListWodRequest $request)
    {
        $tenant = Tenant::findOrFail($request->input('filter.tenant_id'));
        $startDate = Carbon::parse($request->input('filter.starts_after'));
        $endDate = Carbon::parse($request->input('filter.ends_before'));

        if (auth()->check()) {

            $authTenantUser = (new TenantUserService)->getCurrentUserTenantForTenant(auth()->user(), $tenant);

            if (! $authTenantUser) {
                return WodResource::collection(collect()->paginate());
            }

            if ($authTenantUser->isMember()) {

                $programme = Programme::query()->find($request->input('filter.programme_id'));

                if (! $programme instanceof Programme) {
                    return abort(Response::HTTP_BAD_REQUEST, 'Programme is required.');
                }

                if (! (new ProgrammeService())->hasPackageWithProgrammeVisibility($programme, auth()->user())) {
                    return abort(Response::HTTP_NOT_FOUND, 'Your package does not have access to this programme.');
                }
            }

        }

        if ($request->input('filter.use_workout_threshold', false)) {

            $workoutThresholdDate = today()->addDays($tenant->workout_threshold);

            if ($endDate->gt($workoutThresholdDate)) {

                $endDate = $workoutThresholdDate->clone();

                $request->replace([
                    'filter' => [
                        'ends_before' => $workoutThresholdDate->format('Y-m-d'),
                    ],
                ]);
            }
        }

        // Return an empty array if startDate is greater than the endDate
        if ($startDate->gt($endDate)) {
            return WodResource::collection(collect()->paginate());
        }

        return WodResource::collection(
            QueryBuilder::for(Wod::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                    AllowedFilter::scope('starts_after', 'startsAfter'),
                    AllowedFilter::scope('ends_before', 'endsBefore'),
                    AllowedFilter::exact('programme_id'),
                ])
                ->whereHas('programme', function ($query) {
                    $query->where('is_active', 1);
                })
                ->with(['exercises.prefixes', 'programme'])
                ->_paginate()
        );
    }

    public function show(ShowWodRequest $request, Wod $wod)
    {
        return new WodResource($wod->loadMissing(['exercises.prefixes', 'programme']));
    }

    public function update(UpdateWodRequest $request, Wod $wod)
    {
        if ($request->has('exercises')) {
            $this->wodExercise->deactivateAllActiveWodExercises($wod);

            foreach ($request->get('exercises') as $item) {
                $this->wodExercise->updateOrCreateWodExercise($item, $wod);
            }
        }

        $this->wod->update($wod, $request->safe()->except('exercises'));

        return new WodResource($wod->load(['exercises.prefixes', 'programme']));
    }

    public function delete(DeleteWodRequest $request, Wod $wod)
    {
        $this->wod->delete($wod);

        return response()->noContent();
    }
}
