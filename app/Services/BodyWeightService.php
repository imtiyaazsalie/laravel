<?php

namespace App\Services;

use App\Models\BodyWeight;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BodyWeightService
{
    public function list($request)
    {
        return QueryBuilder::for(BodyWeight::class)
            ->allowedFilters(
                [
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::partial('recorded_on', 'recorded_on'),
                ]
            )
            ->_paginate()
            ->appends($request->query());
    }

    public function store($data)
    {
        $bodyWeight = new BodyWeight();
        $bodyWeight->fill($data);
        $bodyWeight->save();

        return $bodyWeight;
    }

    public function update($data, BodyWeight $bodyWeight)
    {
        $bodyWeight->update($data);

        return $bodyWeight;

    }

    public function delete(BodyWeight $bodyWeight)
    {
        $bodyWeight->delete();

        return $bodyWeight;

    }
}
