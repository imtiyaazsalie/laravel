<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExerciseCategory\ExerciseCategoryAssignToExercisesRequest;
use App\Http\Requests\ExerciseCategory\ListExerciseCategoriesRequest;
use App\Http\Requests\ExerciseCategory\ShowExerciseCategoryRequest;
use App\Http\Requests\ExerciseCategory\StoreExerciseCategoryRequest;
use App\Http\Requests\ExerciseCategory\UpdateExerciseCategoriesRequest;
use App\Http\Requests\ExerciseCategory\UpdateExerciseCategoryRequest;
use App\Http\Resources\ExerciseCategoryResource;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExerciseCategoryController extends Controller
{
    public function store(StoreExerciseCategoryRequest $request): ExerciseCategoryResource
    {
        $exerciseCategory = new ExerciseCategory();

        $exerciseCategory->fill($request->only(['name', 'is_benchmark']));
        $exerciseCategory->save();

        return new ExerciseCategoryResource($exerciseCategory);
    }

    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    public function list(ListExerciseCategoriesRequest $request): AnonymousResourceCollection
    {
        return ExerciseCategoryResource::collection(
            QueryBuilder::for(ExerciseCategory::class)
                ->allowedFilters([
                    AllowedFilter::exact('is_active'),
                ])
                ->orderBy('exercise_category_desc')
                ->_paginate()
        );
    }

    public function show(ShowExerciseCategoryRequest $request, ExerciseCategory $exerciseCategory): ExerciseCategoryResource
    {
        return new ExerciseCategoryResource($exerciseCategory);
    }

    public function update(UpdateExerciseCategoryRequest $request, ExerciseCategory $exerciseCategory): ExerciseCategoryResource
    {
        $exerciseCategory->update($request->validated());

        return new ExerciseCategoryResource($exerciseCategory);
    }

    public function updateBulk(UpdateExerciseCategoriesRequest $request): AnonymousResourceCollection
    {
        $categories = ExerciseCategory::whereIn('exercise_category_id', $request->exercise_categories)->get();

        foreach ($categories as $category) {
            switch ($request->get('action')) {
                case 'activate':
                    $category->update(['is_active' => 1]);
                    break;

                case 'deactivate':
                    $category->update(['is_active' => 0]);
                    break;

                case 'benchmark':
                    $category->update(['is_benchmark' => 1]);
                    break;

                case 'not_benchmark':
                    $category->update(['is_benchmark' => 0]);
                    break;

                default:
                    abort(400, 'The specified action is invalid.');
            }
        }

        return ExerciseCategoryResource::collection($categories);
    }

    public function assignToExercises(ExerciseCategoryAssignToExercisesRequest $request, ExerciseCategory $exerciseCategory)
    {
        $exercisesArray = Exercise::whereIn('exercise_id', $request->get('benchmark_exercise_ids'))
            ->where('is_benchmark', '=', true)
            ->update([
                'exercise_category_id' => $exerciseCategory->getKey(),
            ]);

        return response()->noContent();
    }
}
