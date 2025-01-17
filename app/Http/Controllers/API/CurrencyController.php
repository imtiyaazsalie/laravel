<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\ListCurrenciesRequest;
use App\Http\Requests\Currency\ReadCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyController extends Controller
{
    public function list(ListCurrenciesRequest $request)
    {
        $currencies = QueryBuilder::for(Currency::class)
            ->_paginate();

        return CurrencyResource::collection($currencies);
    }

    public function show(ReadCurrencyRequest $request, Currency $currency)
    {
        return new CurrencyResource($currency);
    }
}
