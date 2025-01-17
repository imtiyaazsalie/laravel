<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\WodCapture\Exercises\ApproveAllWodCaptureExercisesRequest;
use App\Http\Requests\WodCapture\Exercises\ApproveWodCaptureExerciseRequest;
use App\Http\Requests\WodCapture\Exercises\ListWodCaptureExercisesRequest;
use App\Http\Requests\WodCapture\Exercises\RejectWodCaptureExerciseRequest;
use App\Http\Requests\WodCapture\Exercises\ShowWodCaptureExerciseRequest;
use App\Http\Requests\WodCapture\Exercises\StoreWodCaptureExerciseRequest;
use App\Http\Requests\WodCapture\Exercises\UpdateWodCaptureExerciseRequest;
use App\Http\Resources\WodCaptureExerciseResource;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Wod;
use App\Models\WodCaptureExercise;
use App\Services\WODCaptureExerciseService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WODCaptureExercisesController extends Controller
{
    public function __construct(private readonly WODCaptureExerciseService $wodCaptureExerciseService)
    {
    }

    public function list(ListWodCaptureExercisesRequest $request)
    {
        return WodCaptureExerciseResource::collection(
            QueryBuilder::for(WodCaptureExercise::class)
                ->where('wod_capture_exercises.is_active', true)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'capture.wod.box_id'),
                    AllowedFilter::exact('exercise_id'),
                    AllowedFilter::exact('wod_id', 'capture.wod_id'),
                    AllowedFilter::exact('user_id', 'capture.user_id'),
                    AllowedFilter::scope('unverified_benchmarks', 'unverifiedBenchmarks'),
                ])
                ->allowedIncludes('exercise', 'capture.user', 'capture.wod')
                ->_paginate()
        );
    }

    public function show(ShowWodCaptureExerciseRequest $request, WodCaptureExercise $wodCaptureExercise)
    {
        return new WodCaptureExerciseResource($this->wodCaptureExerciseService->show($wodCaptureExercise));
    }

    public function store(StoreWodCaptureExerciseRequest $request)
    {
        return new WodCaptureExerciseResource(
            $this->wodCaptureExerciseService->createWodCaptureExercise(
                wod: Wod::findOrFail($request->wod_id),
                exercise: Exercise::findOrFail($request->exercise_id),
                user: User::findOrFail($request->user_id),
                data: $request->validated()
            )
        );
    }

    public function update(UpdateWodCaptureExerciseRequest $request, WodCaptureExercise $wodCaptureExercise)
    {
        $this->wodCaptureExerciseService->update(
            $wodCaptureExercise,
            $request->validated()
        );

        return new WodCaptureExerciseResource($wodCaptureExercise);
    }

    public function approve(ApproveWodCaptureExerciseRequest $request, WodCaptureExercise $wodCaptureExercise)
    {
        $this->wodCaptureExerciseService->approveWodCaptureExercise($wodCaptureExercise);

        return new WodCaptureExerciseResource($wodCaptureExercise);
    }

    public function reject(RejectWodCaptureExerciseRequest $request, WodCaptureExercise $wodCaptureExercise)
    {
        $this->wodCaptureExerciseService->rejectBenchmark($wodCaptureExercise);

        return response()->noContent();
    }

    public function approveAll(ApproveAllWodCaptureExercisesRequest $request)
    {
        $this->wodCaptureExerciseService->approveAllExercises(
            $request->get('tenant_id'),
            $request->get('exercise_id'),
            $request->get('user_id'),
        );

        return response()->noContent();
    }
}
