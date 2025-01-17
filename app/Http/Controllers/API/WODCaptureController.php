<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\WodCapture\ListWodCapturesRequest;
use App\Http\Requests\WodCapture\ShowWodCaptureRequest;
use App\Http\Resources\WodCaptureResource;
use App\Models\WodCapture;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WODCaptureController extends Controller
{
    public function list(ListWodCapturesRequest $request)
    {
        return WodCaptureResource::collection(
            QueryBuilder::for(WodCapture::class)
                ->allowedIncludes('wod', 'user', 'likes.user', 'comments.user', 'exercises')
                ->allowedFilters([
                    AllowedFilter::exact('wod_id'),
                    AllowedFilter::exact('user_id'),
                ])
                ->_paginate()
        );
    }

    public function show(ShowWodCaptureRequest $request, WodCapture $capture)
    {
        return new WodCaptureResource(
            $capture->loadMissing(['wod', 'user', 'likes.user', 'comments.user', 'exercises'])
        );
    }
}
