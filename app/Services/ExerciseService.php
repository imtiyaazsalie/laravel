<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExerciseService
{
    public function store($data)
    {
        $exercise = new Exercise();
        $exercise->fill($data);
        $exercise->save();

        return $exercise;
    }

    public function listByRequest()
    {
        return QueryBuilder::for(Exercise::class)
            ->allowedFilters([
                AllowedFilter::exact('is_benchmark', 'is_benchmark'),
                AllowedFilter::exact('is_active', 'is_active'),
                AllowedFilter::exact('exercise_category_id', 'exercise_category_id'),
                AllowedFilter::partial('search', 'exercise_name'),
                AllowedFilter::scope('type'),
                AllowedFilter::callback('tenant_id', function (Builder $query, $value) {
                    if (request()->input('filter.type') == 'global' && ! auth()->user()->isAdmin()) {
                        $query->whereNull('box_id');
                    } elseif (request()->input('filter.type') == 'owned') {
                        $query->where('box_id', $value);
                    }
                }),
            ])
            ->allowedIncludes('exerciseCategory')
            ->when(! request()->input('filter.type') && ! auth()->user()->isAdmin() && ! request()->input('filter.tenant_id'), function ($query) {
                return $query->orWhereNull('box_id');
            })
            ->when(request()->input('filter.tenant_id') && ! request()->input('filter.type'), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('box_id')
                        ->orWhere('box_id', '=', request()->input('filter.tenant_id'));
                });
            })
            ->whereNotNull('measuring_unit_id')
            ->with(['measureUnit'])
            ->orderBy('exercise.exercise_name')
            ->_paginate();
    }
}
