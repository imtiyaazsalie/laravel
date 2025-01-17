<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonalWod\DeletePersonalWodRequest;
use App\Http\Requests\PersonalWod\ListPersonalWodRequest;
use App\Http\Requests\PersonalWod\ShowPersonalWodRequest;
use App\Http\Requests\PersonalWod\StorePersonalWodRequest;
use App\Http\Requests\PersonalWod\UpdatePersonalWodRequest;
use App\Http\Resources\PersonalWodResource;
use App\Models\PersonalWod;
use App\Services\PersonalWODService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PersonalWODController extends Controller
{
    public function __construct(
        private PersonalWODService $personalWod
    ) {
        //
    }

    public function store(StorePersonalWodRequest $request)
    {
        return new PersonalWodResource(
            $this->personalWod->store($request->validated())
        );
    }

    public function list(ListPersonalWodRequest $request)
    {
        return PersonalWodResource::collection(
            QueryBuilder::for(PersonalWod::class)
                ->allowedFilters([
                    AllowedFilter::partial('search', 'name'),
                    AllowedFilter::exact('user_id', 'user_id'),
                    AllowedFilter::exact('tenant_id', 'box_id'),
                ])
                ->allowedIncludes(
                    'tenant',
                    'user',
                    'measurementUnit'
                )
                ->_paginate()
        );
    }

    public function show(ShowPersonalWodRequest $request, PersonalWod $personalWod)
    {
        return new PersonalWodResource($this->personalWod->show($personalWod));
    }

    public function update(PersonalWod $personalWod, UpdatePersonalWodRequest $request)
    {
        $this->personalWod->update($personalWod, $request->validated());

        return new PersonalWodResource($personalWod->loadMissing([
            'user',
            'measurementUnit',
        ]));
    }

    public function delete(DeletePersonalWodRequest $request, PersonalWod $personalWod)
    {
        $this->personalWod->delete($personalWod);

        return response()->noContent();
    }
}
