<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoronavirusQuestionaireResults\CreateCoronavirusQuestionaireResultRequest;
use App\Http\Requests\CoronavirusQuestionaireResults\DeleteCoronavirusQuestionaireResultRequest;
use App\Http\Requests\CoronavirusQuestionaireResults\ListCoronavirusQuestionaireResultsRequest;
use App\Http\Requests\CoronavirusQuestionaireResults\UpdateCoronavirusQuestionaireResultRequest;
use App\Http\Resources\CoronavirusQuestionnaireResultResource;
use App\Models\CoronavirusQuestionnaireResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CoronavirusQuestionnaireResultsController extends Controller
{
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[class_date_id]', 'integer', required: false)]
    #[QueryParam('filter[date]', 'boolean', required: false)]
    public function list(ListCoronavirusQuestionaireResultsRequest $request): AnonymousResourceCollection
    {
        return CoronavirusQuestionnaireResultResource::collection(
            QueryBuilder::for(CoronavirusQuestionnaireResult::class)
                ->allowedFilters([
                    AllowedFilter::exact('user_id', 'classBooking.user_id'),
                    AllowedFilter::exact('tenant_id', 'classBooking.class.box_id'),
                    AllowedFilter::exact('location_id', 'classBooking.class.box_facility_id'),
                    AllowedFilter::exact('class_date_id', 'classBooking.class_to_date_id'),
                    AllowedFilter::exact('date', 'classBooking.classDate.class_date'),
                ])
                ->allowedIncludes([
                    'classBooking.user',
                    'classBooking.class',
                    'classBooking.class.location',
                    'classBooking.classDate',
                    'createdBy',
                    'updatedBy',
                ])
                ->_paginate()
        );
    }

    public function store(CreateCoronavirusQuestionaireResultRequest $request): CoronavirusQuestionnaireResultResource
    {
        $result = new CoronavirusQuestionnaireResult();

        $result->fill($request->validated());
        $result->save();

        return new CoronavirusQuestionnaireResultResource($result);
    }

    public function update(UpdateCoronavirusQuestionaireResultRequest $request, CoronavirusQuestionnaireResult $result): CoronavirusQuestionnaireResultResource
    {
        $result->update($request->validated());

        return new CoronavirusQuestionnaireResultResource($result);
    }

    public function delete(DeleteCoronavirusQuestionaireResultRequest $request, CoronavirusQuestionnaireResult $result): Response
    {
        $result->delete();

        return response()->noContent();
    }
}
