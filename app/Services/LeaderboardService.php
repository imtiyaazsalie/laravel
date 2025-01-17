<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Exercise;
use App\Models\Leaderboard;
use App\Models\Location;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\WodCaptureExercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    public function listByRequest(): LengthAwarePaginator|array
    {
        $leaderboardResultsByExercise = [];

        $exercise = Exercise::find(request()->input('filter.exercise_id'));

        if (! $exercise instanceof Exercise) {
            // Get all global benchmark exerciser
            $benchmarkExercises = Exercise::query()
                ->withoutGlobalScopes()
                ->join('measuring_units', 'measuring_units.measuring_unit_id', '=', 'exercise.measuring_unit_id')
                ->where('exercise.is_benchmark', '=', true)
                ->whereNull('exercise.box_id')
                ->where('exercise.is_active', '=', true)
                ->orderBy('exercise.exercise_name')
                ->get();

            foreach ($benchmarkExercises as $exercise) {

                $currentUsersLeaderboardResult = null;

                // Only for app which is for gym members
                $currentUsersLeaderboardResult = Leaderboard::query()
                    ->addSelect(DB::raw('0 + leaderboard.score AS score'))
                    ->select('leaderboard.*')
                    ->join('users', 'leaderboard.user_id', '=', 'users.user_id')
                    ->join('user_to_box', 'user_to_box.user_id', '=', 'users.user_id')
                    ->where('leaderboard.exercise_id', '=', $exercise->getKey())
                    ->where('leaderboard.user_id', '=', auth()->user()->getAuthIdentifier())
                    ->where('leaderboard.is_active', '=', true)
                    ->whereRaw('CURDATE() BETWEEN user_to_box.effective_date and user_to_box.end_date')
                    ->when($exercise->measureUnit->name == 'For Time - min', function ($query) {
                        $query->orderBy('score');
                    })
                    ->when($exercise->measureUnit->name != 'For Time - min', function ($query) {
                        $query->orderByDesc('score');
                    })
                    ->limit(1)
                    ->with('user')
                    ->first();

                // Get the top 5 leaderboard results for exercise
                $box = Tenant::find(request()->input('filter.tenant_id'));
                $boxFacility = Location::find(request()->input('filter.location_id'));
                $region = Region::find(request()->input('filter.region_id'));

                // Get leaderboard results
                $leaderboardResults = Leaderboard::query()
                    ->addSelect(DB::raw('0 + leaderboard.score AS score'))
                    ->select(['leaderboard.*', 'boxes.*'])
                    ->join('users', 'leaderboard.user_id', '=', 'users.user_id')
                    ->join('user_to_box', 'user_to_box.user_id', '=', 'users.user_id')
                    ->join('boxes', 'boxes.box_id', '=', 'user_to_box.box_id')
                    ->where('leaderboard.exercise_id', '=', $exercise->getKey())
                    ->where('leaderboard.is_active', '=', true)
                    ->where('user_to_box.user_type_id', '!=', UserType::LEAD_MEMBER)
                    ->whereRaw('CURDATE() BETWEEN user_to_box.effective_date and user_to_box.end_date')
                    ->limit(5)
                    ->when($exercise->measureUnit->name == 'For Time - min', function ($query) {
                        $query->orderBy('score');
                    })
                    ->when($exercise->measureUnit->name != 'For Time - min', function ($query) {
                        $query->orderByDesc('score');
                    })
                    ->when($box, function (Builder $query) use ($box) {
                        $query->where('user_to_box.box_id', '=', $box->getKey());
                    })
                    ->when($boxFacility, function (Builder $query) use ($boxFacility) {
                        $query->join('user_to_facility', 'user_to_facility.user_id', '=', 'users.user_id')
                            ->where('user_to_facility.box_facility_id', '=', $boxFacility->getKey())
                            ->whereRaw('CURDATE() BETWEEN user_to_facility.effective_date and user_to_facility.end_date');

                    })
                    ->when($region, function (Builder $query) use ($region) {
                        $query->where('user_to_box.region_id', '=', $region->getKey());
                    })
                    ->when(request()->input('filter.gender_id'), function (Builder $query) {
                        $query->where('users.gender_id', '=', request()->input('filter.gender_id'));
                    })
                    ->with('user')
                    ->get();

                if ($currentUsersLeaderboardResult instanceof Leaderboard) {
                    $leaderboardResults[] = $currentUsersLeaderboardResult;
                }

                // Serialize the data
                foreach ($leaderboardResults as $index => $leaderboardResult) {
                    $leaderboardResult->position = ++$index;
                    $leaderboardResultsByExercise[] = $leaderboardResult;
                }
            }
        } else {
            $box = Tenant::find(request()->input('filter.tenant_id'));
            $boxFacility = Location::find(request()->input('filter.location_id'));
            $region = Region::find(request()->input('filter.region_id'));

            // Get leaderboard results
            $leaderboardResults = Leaderboard::query()
                ->addSelect(DB::raw('0 + leaderboard.score AS score'))
                ->select(['leaderboard.*', 'boxes.*'])
                ->join('users', 'leaderboard.user_id', '=', 'users.user_id')
                ->join('user_to_box', 'user_to_box.user_id', '=', 'users.user_id')
                ->join('boxes', 'boxes.box_id', '=', 'user_to_box.box_id')
                ->where('leaderboard.exercise_id', '=', $exercise->getKey())
                ->where('leaderboard.is_active', '=', true)
                ->where('user_to_box.user_type_id', '!=', UserType::LEAD_MEMBER)
                ->whereRaw('CURDATE() BETWEEN user_to_box.effective_date and user_to_box.end_date')
                ->when($exercise->measureUnit->name == 'For Time - min', function (Builder $query) {
                    $query->orderBy(DB::raw('CAST(score AS DOUBLE)'));
                }, function ($query) {
                    $query->orderByDesc(DB::raw('CAST(score AS DOUBLE)'));
                })
                ->when($box, function (Builder $query) use ($box) {
                    $query->where('user_to_box.box_id', '=', $box->getKey());
                })
                ->when($boxFacility, function (Builder $query) use ($boxFacility) {
                    $query->join('user_to_facility', 'user_to_facility.user_id', '=', 'users.user_id')
                        ->where('user_to_facility.box_facility_id', '=', $boxFacility->getKey())
                        ->whereRaw('CURDATE() BETWEEN user_to_facility.effective_date and user_to_facility.end_date')
                        ->groupBy('leaderboard.user_id');

                })
                ->when($region, function (Builder $query) use ($region) {
                    $query->where('boxes.region_id', '=', $region->getKey());
                })
                ->when(request()->input('filter.gender_id'), function (Builder $query) {
                    $query->where('users.gender_id', '=', request()->input('filter.gender_id'));
                })
                ->with('user')
                ->get();

            // Serialize the data
            foreach ($leaderboardResults as $index => $leaderboardResult) {
                $leaderboardResult->position = ++$index;
                $leaderboardResultsByExercise[] = $leaderboardResult;
            }
        }

        return collect($leaderboardResultsByExercise)->paginate();
    }

    public function captureScoreOnLeaderboard($userId, $exerciseId, $score)
    {
        $currentBenchmark = Leaderboard::where('user_id', '=', $userId)
            ->where('exercise_id', '=', $exerciseId)
            ->first();

        if ($currentBenchmark) {
            if ((float) $currentBenchmark->score < (float) $score) {

                $currentBenchmark->delete();
            }
        } else {
            Leaderboard::create([
                'user_id' => $userId,
                'exercise_id' => $exerciseId,
                'score' => $score,
                'rank' => 0,
            ]);
        }
    }

    public function createLeaderboardIfEligible(WodCaptureExercise $wodCaptureExercise)
    {
        $wodCaptureExercise->loadMissing('exercise', 'capture');

        $leaderboard = Leaderboard::where('user_id', '=', $wodCaptureExercise->capture->user_id)
            ->where('exercise_id', '=', $wodCaptureExercise->exercise_id)
            ->first();

        //not eligible for leadboard
        if ($wodCaptureExercise->exercise->isNotBenchmark()
            || $wodCaptureExercise->isNotPersonalBest()
            || $wodCaptureExercise->isNotRx()
        ) {

            if ($leaderboard) {
                $leaderboard->delete();
            }

            return;
        }

        if ($leaderboard) {
            $leaderboard->score = $wodCaptureExercise->score;
            $leaderboard->save();

            return;
        }

        Leaderboard::create([
            'user_id' => $wodCaptureExercise->capture->user_id,
            'exercise_id' => $wodCaptureExercise->exercise_id,
            'rank' => 0,
            'score' => $wodCaptureExercise->score,
        ]);
    }

    public function delete(Leaderboard $leaderboard)
    {
        $leaderboard->delete();
    }

    public function store($data)
    {
        $leaderboard = new Leaderboard();
        $leaderboard->fill($data);
        $leaderboard->save();
    }
}
