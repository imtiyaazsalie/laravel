<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\OwnBenchmark\ApproveAllOwnBenchmarksRequest;
use App\Http\Requests\OwnBenchmark\ApproveOwnBenchmarkRequest;
use App\Http\Requests\OwnBenchmark\CreateOwnBenchmarkRequest;
use App\Http\Requests\OwnBenchmark\DeleteOwnBenchmarkRequest;
use App\Http\Requests\OwnBenchmark\ListOwnBenchmarksRequest;
use App\Http\Requests\OwnBenchmark\ReadOwnBenchmarkRequest;
use App\Http\Requests\OwnBenchmark\RejectOwnBenchmarkRequest;
use App\Http\Requests\OwnBenchmark\UpdateOwnBenchmarkRequest;
use App\Http\Resources\OwnBenchmarkResource;
use App\Models\OwnBenchmark;
use App\Models\Tenant;
use App\Services\OwnBenchmarkService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OwnBenchmarkController extends Controller
{
    private OwnBenchmarkService $ownBenchmark;

    public function __construct(OwnBenchmarkService $ownBenchmarkService)
    {
        $this->ownBenchmark = $ownBenchmarkService;
    }

    public function store(CreateOwnBenchmarkRequest $request)
    {
        return new OwnBenchmarkResource(
            $this->ownBenchmark->store([
                'user_id' => $request->get('user_id'),
                'exercise_id' => $request->get('exercise_id'),
                'score' => $request->get('score'),
                'is_rx' => $request->get('is_rx'),
                'own_benchmark_date' => $request->input('date', now()),
                'note' => $request->get('note'),
                'is_verified' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')),
                'verifier_id' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')) ? auth()->user()->getAuthIdentifier() : null,
                'dt_verified' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')) ? now() : null,
            ])
        );
    }

    public function approve(ApproveOwnBenchmarkRequest $request, OwnBenchmark $ownBenchmark)
    {

        if ($ownBenchmark->is_verified) {
            return response()->errorMessage('Benchmark already approved.');
        }

        if (! $ownBenchmark->is_active) {
            return response()->errorMessage('Benchmark is not active.');
        }

        if (! $ownBenchmark->is_rx) {
            return response()->errorMessage('Benchmark is not RX.');
        }

        $this->ownBenchmark->approve($ownBenchmark);

        return response()->noContent();
    }

    public function approveAll(ApproveAllOwnBenchmarksRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $this->ownBenchmark->approveAll(
            $tenant,
            $request->get('user_id'),
            $request->get('exercise_id')
        );

        return response()->noContent();
    }

    public function rejectBenchmark(RejectOwnBenchmarkRequest $request, OwnBenchmark $ownBenchmark)
    {
        $this->ownBenchmark->rejectBenchmark($ownBenchmark);

        return response()->noContent();
    }

    public function update(OwnBenchmark $ownBenchmark, UpdateOwnBenchmarkRequest $request)
    {
        $this->ownBenchmark->update($ownBenchmark, [
            'user_id' => $request->get('user_id'),
            'exercise_id' => $request->get('exercise_id'),
            'score' => $request->get('score'),
            'is_rx' => $request->get('is_rx'),
            'own_benchmark_date' => $request->input('date', now()),
            'note' => $request->get('note'),
            'is_verified' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')),
            'verifier_id' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')) ? auth()->user()->getAuthIdentifier() : null,
            'dt_verified' => ! ($request->get('is_rx') && $request->get('is_considered_for_leaderboard')) ? now() : null,
        ]);

        return new OwnBenchmarkResource($ownBenchmark);
    }

    public function show(ReadOwnBenchmarkRequest $request, OwnBenchmark $ownBenchmark)
    {
        return new OwnBenchmarkResource($this->ownBenchmark->show($ownBenchmark));
    }

    public function delete(DeleteOwnBenchmarkRequest $request, OwnBenchmark $ownBenchmark)
    {
        $this->ownBenchmark->delete($ownBenchmark);

        return response()->noContent();
    }

    public function list(ListOwnBenchmarksRequest $request)
    {
        return OwnBenchmarkResource::collection(
            QueryBuilder::for(OwnBenchmark::class)
                ->allowedFilters([
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('exercise_id', 'exercise_id'),
                ])
                ->_paginate()
        );
    }
}
