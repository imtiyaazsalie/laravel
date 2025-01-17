<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\OwnBenchmark;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class OwnBenchmarkService
{
    private LeaderboardService $leaderboard;

    public function __construct()
    {
        $this->leaderboard = (new LeaderboardService());
    }

    public function store($data)
    {
        $ownBenchmark = new OwnBenchmark();
        $ownBenchmark->fill($data);
        $ownBenchmark->save();

        return $ownBenchmark;
    }

    public function approve(OwnBenchmark $ownBenchmark)
    {
        $this->update($ownBenchmark, [
            'is_verified' => true,
            'verifier_id' => auth()->user()->getAuthIdentifier(),
            'dt_verified' => now(),
        ]);

        if ($ownBenchmark->user_id > 0) {
            $this->leaderboard->captureScoreOnLeaderboard($ownBenchmark->user_id, $ownBenchmark->exercise_id, $ownBenchmark->score);
        }

        return $ownBenchmark;
    }

    public function update(OwnBenchmark $ownBenchmark, $data)
    {
        $ownBenchmark->update($data);

        return $ownBenchmark;
    }

    public function approveAll(Tenant $box, $userId = null, $exerciseId = null)
    {
        $benchmarks = OwnBenchmark::query()
            ->leftJoin('users', 'users.user_id', '=', 'own_benchmarks.user_id')
            ->leftJoin('user_to_box', 'own_benchmarks.user_id', '=', 'user_to_box.user_id')
            ->where('own_benchmarks.is_verified', '=', false)
            ->where('own_benchmarks.is_rx', '=', true)
            ->where('own_benchmarks.is_active', '=', true)
            ->where('user_to_box.box_id', '=', $box->getKey())
            ->when(! empty($userId), function ($query) use ($userId) {
                return $query->where('own_benchmarks.user_id', '=', $userId);
            })
            ->when(! empty($exerciseId), function ($query) use ($exerciseId) {
                return $query->where('own_benchmarks.exercise_id', '=', $exerciseId);
            })
            ->get();

        foreach ($benchmarks as $benchmark) {
            $this->approve($benchmark);
        }
    }

    public function rejectBenchmark(OwnBenchmark $ownBenchmark)
    {
        $this->update($ownBenchmark, [
            'is_verified' => true,
            'verifier_id' => auth()->user()->getAuthIdentifier(),
            'dt_verified' => now(),

        ]);

        return $ownBenchmark;
    }

    public function show(OwnBenchmark $ownBenchmark)
    {
        return $ownBenchmark;
    }

    public function delete(OwnBenchmark $ownBenchmark)
    {
        $ownBenchmark->delete();
    }

    public function list()
    {
        return QueryBuilder::for(OwnBenchmark::class)
            ->_paginate();
    }

    public function getOwnBenchmarksForUser(User $user, ?Exercise $exercise = null, ?string $search = null, array $relations = []): Collection
    {
        return OwnBenchmark::query()
            ->leftJoin('exercise', 'own_benchmarks.exercise_id', '=', 'exercise.exercise_id')
            ->leftJoin('exercise_category', 'exercise.exercise_category_id', '=', 'exercise_category.exercise_category_id')
            ->leftJoin('measuring_units', 'exercise.measuring_unit_id', '=', 'measuring_units.measuring_unit_id')
            ->where('own_benchmarks.user_id', '=', $user->getKey())
            ->where('own_benchmarks.is_active', '=', 1)
            ->when($exercise, function ($query) use ($exercise) {
                $query->where('own_benchmarks.exercise_id', '=', $exercise->getKey());
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('exercise.exercise_name', 'like', "%{$search}%")
                        ->orWhere('exercise.exercise_desc', 'like', "%{$search}%");
                });
            })
            ->when(! empty($relations), function ($query) use ($relations) {
                $query->with($relations);
            })
            ->select('own_benchmarks.*')
            ->addSelect(
                // 'own_benchmarks.own_benchmark_id',
                'exercise.exercise_id',
                // 'own_benchmarks.user_id',
                'exercise.is_benchmark',
                'exercise.exercise_name',
                'exercise.exercise_desc',
                'exercise.rx_male',
                'exercise.rx_female',
                'exercise.resource_url',
                'exercise_category.exercise_category_id',
                'exercise_category.exercise_category_desc',
                'measuring_units.measuring_unit_id',
                'measuring_units.measuring_unit_desc',
                'measuring_units.measuring_unit_short',
                // 'own_benchmarks.is_rx',
                // 'own_benchmarks.score',
                // 'own_benchmarks.created_on',
                DB::raw("'own_exercise' as type"),
                // 'own_benchmarks.note'
            )->get();

    }
}
