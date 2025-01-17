<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoronavirusVaccinationDetails\CreateCoronavirusVaccinationDetailsRequest;
use App\Http\Requests\CoronavirusVaccinationDetails\DeleteCoronavirusVaccinationDetailsRequest;
use App\Http\Requests\CoronavirusVaccinationDetails\DownloadCoronavirusVaccinationDetailsRequest;
use App\Http\Requests\CoronavirusVaccinationDetails\ListCoronavirusVaccinationDetailsRequest;
use App\Http\Requests\CoronavirusVaccinationDetails\UpdateCoronavirusVaccinationDetailsRequest;
use App\Http\Resources\CoronavirusVaccinationDetailsResource;
use App\Models\CoronavirusVaccinationDetails;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CoronavirusVaccinationDetailsController extends Controller
{
    #[QueryParam('filter[status]', 'integer', required: false)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    public function list(ListCoronavirusVaccinationDetailsRequest $request)
    {
        $result = QueryBuilder::for(
            CoronavirusVaccinationDetails::class
        )
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('location_id', 'user.location.box_facility_id'),
            ])
            ->allowedIncludes([
                'user',
                'createdBy',
                'updatedBy',
                'tenant',
            ])
            ->_paginate();

        return CoronavirusVaccinationDetailsResource::collection($result);
    }

    public function store(CreateCoronavirusVaccinationDetailsRequest $request)
    {
        if ($request->has('file')) {

            $fileName = md5($request->user_id.time()).'.'.$request->file('file')->getClientOriginalExtension();
            $filePath = 'coronavirus-vaccination-details/';

            $request->file('file')->storePubliclyAs($filePath, $fileName, [
                'disk' => 'private',
                'visibility' => 'private',
            ]);
        }

        $result = CoronavirusVaccinationDetails::create([
            ...$request->validated(),
            'file' => $request->has('file') ? $filePath.$fileName : null,
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'updated_by_id' => auth()->user()->getAuthIdentifier(),
        ]);

        return new CoronavirusVaccinationDetailsResource($result->loadMissing([
            'user',
            'createdBy',
            'updatedBy',
        ]));
    }

    public function download(DownloadCoronavirusVaccinationDetailsRequest $request, CoronavirusVaccinationDetails $result)
    {
        return response()->json([
            'file' => $result->file_url,
        ]);
    }

    public function update(CoronavirusVaccinationDetails $result, UpdateCoronavirusVaccinationDetailsRequest $request)
    {

        if ($request->has('file')) {

            $fileName = md5($request->user_id.time()).'.'.$request->file('file')->getClientOriginalExtension();
            $filePath = 'coronavirus-vaccination-details/';

            $request->file('file')->storePubliclyAs($filePath, $fileName, [
                'disk' => 'private',
                'visibility' => 'private',
            ]);

            if ($result->file) {
                Storage::disk('private')->delete($result->file);
            }
        }

        $result->update([
            ...$request->safe()->only('status'),
            'file' => $request->has('file') ? $filePath.$fileName : $result->file,
            'updated_by_id' => auth()->user()->getAuthIdentifier(),
        ]);

        return new CoronavirusVaccinationDetailsResource($result->loadMissing([
            'user',
            'createdBy',
            'updatedBy',
        ]));
    }

    public function delete(DeleteCoronavirusVaccinationDetailsRequest $request, CoronavirusVaccinationDetails $result)
    {
        if ($result->file) {
            Storage::disk('private')->delete($result->file);
        }

        $result->delete();

        return response()->noContent();
    }
}
