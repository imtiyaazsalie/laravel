<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timezone\ListTimezonesRequest;
use App\Http\Requests\Timezone\ShowTimezoneRequest;
use App\Http\Resources\TimezoneResource;
use App\Models\Timezone;
use Spatie\QueryBuilder\QueryBuilder;

class TimezoneController extends Controller
{
    public function list(ListTimezonesRequest $request)
    {
        return TimezoneResource::collection(
            QueryBuilder::for(Timezone::class)
                ->_paginate()
        );
    }

    public function show(ShowTimezoneRequest $request, Timezone $timezone)
    {
        return new TimezoneResource($timezone);
    }
}
