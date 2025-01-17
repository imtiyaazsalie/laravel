<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogBook\ListLogBookRequest;
use App\Http\Resources\LogBookResource;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\MeasurementUnit;
use App\Models\OwnBenchmark;
use App\Models\PersonalWod;
use App\Models\Tenant;
use App\Models\WodCaptureExercise;
use App\Services\OwnBenchmarkService;
use App\Services\PersonalWODService;
use App\Services\TenantUserService;
use App\Services\WODCaptureExerciseService;
use Illuminate\Http\Response;

class LogBookController extends Controller
{
    public function list(ListLogBookRequest $request)
    {
        $data = [];

        $tenantId = $request->input('filter.tenant_id');
        $userId = $request->input('filter.user_id');
        $exercise = $request->input('filter.exercise_id') ? Exercise::findOrFail($request->input('filter.exercise_id')) : null;
        $type = $request->input('filter.type');
        $search = $request->input('filter.search');
        $isOnlyReturnBest = $request->input('filter.is_only_return_best', false);

        // Get user entity
        $user = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenantId)?->user;

        if (! $user) {
            return response()->errorMessage('User not found', Response::HTTP_BAD_REQUEST);
        }

        // Get wodCaptures, personal wods and own benchmarks for user
        $arrayOfArrays = collect([
            (new WODCaptureExerciseService())->getWodCaptureExercisesForUser($user, null, $exercise, $search, ['capture.wod', 'exercise.tenant']),
            $exercise ? [] : (new PersonalWODService())->getAllPersonalWodsForUser($user, $search),
            (new OwnBenchmarkService())->getOwnBenchmarksForUser($user, $exercise, $search, ['exercise.tenant']),
        ]);

        // Merge them into one array
        $allExerciseResults = $arrayOfArrays->flatten(1);

        // Determine which types to include
        $includeBenchmarks = ! $type ? true : $type === 'benchmarkExercises';
        $includeWod = ! $type ? true : $type === 'wodExercises';
        $includePersonal = ! $type ? true : $type === 'personalExercises';

        // Only get the best scores
        if ($isOnlyReturnBest) {
            $bestExercises = [];

            foreach ($allExerciseResults as $exerciseResult) {

                // If personal exercise
                if ($exerciseResult instanceof PersonalWod) {
                    $bestExercises[] = $exerciseResult;

                    continue;
                }

                // If exercise is not here yet then add it
                if (! isset($bestExercises[$exerciseResult->exercise_id])) {
                    $bestExercises[$exerciseResult->exercise_id] = $exerciseResult;

                    continue;
                }

                $bestResult = $bestExercises[$exerciseResult->exercise_id];

                // If exercise is here then compare the scores to get the best score
                if ((new WODCaptureExerciseService())->isCaptureBetter($exerciseResult->exercise, $bestResult->score, $bestResult->is_rx, $exerciseResult->score, $exerciseResult->is_rx)) {
                    $bestExercises[$exerciseResult->exercise_id] = $exerciseResult;
                }
            }

            $allExerciseResults = $bestExercises;
        }

        // Convert data to arrays
        foreach ($allExerciseResults as $index => $result) {
            if ($result instanceof WodCaptureExercise || $result instanceof OwnBenchmark) {
                if ($result->exercise?->isBenchmark() && ! $result->exercise->tenant instanceof Tenant) {
                    if (! $includeBenchmarks) {
                        continue;
                    }

                    $exerciseType = 'benchmarkExercise';
                } elseif ($result->exercise->isBenchmark() && $result->exercise->tenant instanceof Tenant) {
                    if (! $includeBenchmarks) {
                        continue;
                    }

                    $exerciseType = 'boxBenchmarkExercise';
                } else {
                    if (! $includeWod) {
                        continue;
                    }

                    $exerciseType = 'wodExercise';
                }

                $exercise = $result->exercise;
                $date = $result instanceof WodCaptureExercise ? $result->capture->wod->date->toDateTimeString() : $result->date;

                $data[$index] = [
                    'exercise' => [
                        'id' => $exercise->getKey(),
                        'is_benchmark' => $exercise->isBenchmark(),
                        'name' => $exercise->name,
                        'description' => $exercise->description,
                        'rx_male' => $exercise->rx_male,
                        'rx_female' => $exercise->rx_female,
                        'resource_url' => $exercise->resource_url,
                        'category' => null,
                        'measuring_unit' => null,
                    ],
                    'id' => $result->getKey(),
                    'is_rx' => $result->is_rx,
                    'score' => $result->score,
                    'date' => $date,
                    'type' => $exerciseType,
                    'note' => $result->note,
                ];

                if ($exercise->exerciseCategory instanceof ExerciseCategory) {
                    $data[$index]['exercise']['category'] = [
                        'id' => $exercise->exerciseCategory->getKey(),
                        'name' => $exercise->exerciseCategory->name,
                    ];
                }

                if ($exercise->measureUnit instanceof MeasurementUnit) {
                    $data[$index]['exercise']['measuring_unit'] = [
                        'id' => $exercise->measureUnit->getKey(),
                        'name' => $exercise->measureUnit->name,
                        'short_name' => $exercise->measureUnit->unit,
                    ];
                }
            }

            if ($result instanceof PersonalWod && $includePersonal) {
                $date = $result->date?->toDateTimeString() ?: $result->created_on->toDateTimeString();

                $data[$index] = [
                    'exercise' => [
                        'id' => null,
                        'name' => $result->name,
                        'description' => $result->description,
                        'measuring_unit' => null,
                    ],
                    'is_rx' => $result->is_rx,
                    'score' => $result->score,
                    'date' => $date,
                    'note' => $result->note,
                    'type' => 'personalExercise',
                ];

                if ($result->measurementUnit instanceof MeasurementUnit) {
                    $data[$index]['exercise']['measuring_unit'] = [
                        'id' => $result->measurementUnit->getKey(),
                        'name' => $result->measurementUnit->name,
                        'short_name' => $result->measurementUnit->unit,
                    ];
                }
            }
        }

        // Sort according to most recent date
        usort($data, function ($a, $b) {
            return strtotime($a['date']) < strtotime($b['date']);
        });

        $data = array_values($data);

        return LogBookResource::collection(
            collect($data)->paginate()
        );
    }
}
