<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\WodCapture\Likes\ListWodCaptureLikesRequest;
use App\Http\Requests\WodCapture\Likes\StoreOrDeleteWodCaptureLikeRequest;
use App\Http\Resources\WodCaptureLikeResource;
use App\Models\WodCapture;
use App\Models\WodCaptureLikes;
use App\Services\WODCaptureLikeService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WODCaptureLikeController extends Controller
{
    public function __construct(
        private WODCaptureLikeService $wodCaptureLike
    ) {
        //
    }

    public function storeOrDelete(StoreOrDeleteWodCaptureLikeRequest $request)
    {
        $wodCapture = WodCapture::find($request->wod_capture_id);

        $this->wodCaptureLike->storeOrDelete($wodCapture, auth()->user());

        return response()->noContent();
    }

    public function list(ListWodCaptureLikesRequest $request)
    {
        return WodCaptureLikeResource::collection(
            QueryBuilder::for(WodCaptureLikes::class)
                ->allowedIncludes('user')
                ->allowedFilters([
                    AllowedFilter::exact('wod_capture_id'),
                ])->_paginate()
        );
    }
}
