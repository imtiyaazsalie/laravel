<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\OwnBenchmark;
use App\Models\User;
use App\Models\Wod;
use App\Models\WodCapture;
use App\Models\WodCaptureExercise;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WODCaptureExerciseService
{
    public function createWodCaptureExercise(Wod $wod, Exercise $exercise, User $user, $data)
    {
        $isVerified = boolval(! Arr::get($data, 'is_rx') && ! empty($exercise->is_benchmark));

        //check if user has an existing wod capture
        $wodCapture = WodCapture::query()
            ->where('wod_id', $wod->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if (! $wodCapture) {
            $wodCapture = WodCapture::create([
                'wod_id' => $wod->getKey(),
                'user_id' => $user->getKey(),
                'capturer_id' => auth()->user()->getAuthIdentifier(),
                'is_verified' => $isVerified,
                'dt_verified' => ! $isVerified ? now() : null,
                'verifier_id' => ! $isVerified ? auth()->user()->getAuthIdentifier() : null,
            ]);
        }

        $wodCaptureExerciseData = [
            'wod_capture_id' => $wodCapture->getKey(),
            'exercise_id' => $exercise->getKey(),
            'is_rx' => $data['is_rx'],
            'score' => $data['score'],
            'note' => Arr::get($data, 'note'),
            'is_verified' => $isVerified,
            'dt_verified' => ! $isVerified ? now() : null,
            'verifier_id' => ! $isVerified ? auth()->user()->getAuthIdentifier() : null,
        ];

        $wodCaptureExercise = WodCaptureExercise::create($wodCaptureExerciseData);

        $this->updateUsersPersonalBestForExercise($user, $exercise);

        $wodCaptureExercise = $wodCaptureExercise->fresh();

        (new LeaderboardService())->createLeaderboardIfEligible($wodCaptureExercise);

        return $wodCaptureExercise->withoutRelations();
    }

    private function updateUsersPersonalBestForExercise(User $user, Exercise $exercise): void
    {
        if ($exercise->isNotPersonalBest()) {
            return;
        }

        $wodCaptureExercises = WodCaptureExercise::query()
            ->active()
            ->joinRelationship('capture')
            ->where('exercise_id', $exercise->getKey())
            ->where('wod_capture.user_id', $user->getAuthIdentifier())
            ->get();

        $ownBenchmarks = OwnBenchmark::query()
            ->active()
            ->where('exercise_id', $exercise->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->get();

        if ($wodCaptureExercises->isEmpty() && $ownBenchmarks->isEmpty()) {
            return;
        }

        $best = null;

        $wodCaptureExercises->merge($ownBenchmarks)->each(function ($capture) use (&$best, $exercise) {
            if (! $best) {
                $best = $capture;

                return;
            }

            if ($this->isCaptureBetter($exercise, $best->score, $best->isRx(), $capture->score, $capture->isRx())) {
                $best = $capture;
            }
        });

        //remove personal best from previous capture exercises
        if ($wodCaptureExercises->isNotEmpty()) {
            $wodCaptureExercises->toQuery()->update([
                'is_pb' => true,
            ]);
        }

        //set the best
        if ($best instanceof WodCaptureExercise) {
            $best->update([
                'is_pb' => true,
            ]);
        }
    }

    public function isCaptureBetter(Exercise $exercise, $score, $isRx, $comparedScore, $comparedIsRx): bool
    {
        if ($isRx && ! $comparedIsRx) {
            return false;
        } elseif (! $isRx && $comparedIsRx) {
            return true;
        }

        $exercise->loadMissing('measureUnit');

        if (in_array($exercise->measureUnit->unit, ['kg', 'ml', 'reps', 'rounds', 'm', 'km', 'laps', 'rnds.reps', 'cal', 'm:cm', 'Lbs'])) {
            // greater is better
            return ! (floatval($score) > floatval($comparedScore));
        } else {
            // less is better
            return ! (floatval($score) < floatval($comparedScore));
        }
    }

    public function store($data): WodCaptureExercise
    {
        $WodCaptureExercise = new WodCaptureExercise();
        $WodCaptureExercise->fill($data);
        $WodCaptureExercise->save();

        return $WodCaptureExercise;
    }

    public function approveWodCaptureExercise(WodCaptureExercise $wodCaptureExercise): void
    {
        if ($wodCaptureExercise->capture()->exists() && $wodCaptureExercise->exercise()->exists()) {
            $wodCaptureExercise->update([
                'is_verified' => true,
                'verifier_id' => auth()->user()->getAuthIdentifier(),
                'dt_verified' => now(),
            ]);

            (new LeaderboardService())->captureScoreOnLeaderboard(
                $wodCaptureExercise->capture->user_id,
                $wodCaptureExercise->exercise_id,
                $wodCaptureExercise->score
            );
        }
    }

    public function update(WodCaptureExercise $wodCaptureExercise, $data): WodCaptureExercise
    {
        $wodCaptureExercise->loadMissing('capture.user', 'exercise');

        if (Arr::get($data, 'is_rx') && $wodCaptureExercise->exercise->isBenchmark()) {
            $data['is_verified'] = false;
            $data['dt_verified'] = null;
            $data['verifier_id'] = null;
        } else {
            $data['is_verified'] = true;
            $data['dt_verified'] = now();
            $data['verifier_id'] = auth()->user()->getAuthIdentifier();
        }

        $wodCaptureExercise->update($data);

        $this->updateUsersPersonalBestForExercise(
            $wodCaptureExercise->capture->user,
            $wodCaptureExercise->exercise
        );

        (new LeaderboardService())->createLeaderboardIfEligible($wodCaptureExercise->fresh());

        return $wodCaptureExercise;
    }

    public function approveAllExercises(int $tenantId, $exerciseId, $userId): void
    {
        $wodCaptureExercises = WodCaptureExercise::query()
            ->joinRelationship('capture.wod')
            ->unverifiedBenchmarks()
            ->where('wods.box_id', $tenantId)
            ->when(! empty($userId), function ($query) use ($userId) {
                return $query->where('wod_capture.user_id', $userId);
            })
            ->when(! empty($exerciseId), function ($query) use ($exerciseId) {
                return $query->where('wod_capture_exercises.exercise_id', $exerciseId);
            })
            ->where('score', '>', 0)
            ->get();

        if ($wodCaptureExercises->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($wodCaptureExercises) {
            $leaderboardService = (new LeaderboardService());

            $wodCaptureExercises->toQuery()->update([
                'is_verified' => true,
                'verifier_id' => auth()->user()->getAuthIdentifier(),
                'dt_verified' => now(),
            ]);

            $wodCaptureExercises->each(function ($wodCaptureExercise) use (&$leaderboardService) {
                $leaderboardService->captureScoreOnLeaderboard(
                    $wodCaptureExercise->capture->user_id,
                    $wodCaptureExercise->exercise->exercise_id,
                    $wodCaptureExercise->score
                );
            });
        });
    }

    public function rejectBenchmark(WodCaptureExercise $wodCaptureExercise): WodCaptureExercise
    {
        $this->update($wodCaptureExercise, [
            'is_verified' => true,
            'verifier_id' => auth()->user()->getAuthIdentifier(),
            'dt_verified' => now(),
        ]);

        return $wodCaptureExercise;
    }

    public function show(WodCaptureExercise $wodCaptureExercise): WodCaptureExercise
    {
        return $wodCaptureExercise->loadMissing(['exercise', 'capture.wod', 'capture.user']);
    }

    public function getPBWodCaptureExerciseForBox($box, $startDate, $endDate): Collection|array
    {
        return WodCaptureExercise::query()
            ->from('wod_capture_exercises', 'wce')
            ->join('exercise as e', 'e.exercise_id', '=', 'wce.exercise_id')
            ->join('measuring_units as mu', 'mu.measuring_unit_id', '=', 'e.measuring_unit_id')
            ->join('wod_capture as wc', 'wc.wod_capture_id', '=', 'wce.wod_capture_id')
            ->join('wods as w', 'w.wod_id', '=', 'wc.wod_id')
            ->join('users as u', 'u.user_id', '=', 'wc.user_id')
            ->where('wce.is_active', true)
            ->where('w.box_id', $box)
            ->where('wce.is_pb', true)
            ->whereBetween('w.wod_date', [$startDate, $endDate])
            ->orderBy('wc.dt_added')
            ->get();

    }

    public function getWodCaptureExercisesForUser(User $user, ?Wod $wod, ?Exercise $exercise, ?string $search = null, array $relations = []): Collection
    {
        return WodCaptureExercise::query()
            ->join('wod_capture', 'wod_capture_exercises.wod_capture_id', '=', 'wod_capture.wod_capture_id')
            ->join('exercise', 'wod_capture_exercises.exercise_id', '=', 'exercise.exercise_id')
            ->leftJoin('exercise_category', 'exercise.exercise_category_id', '=', 'exercise_category.exercise_category_id')
            ->leftJoin('measuring_units', 'exercise.measuring_unit_id', '=', 'measuring_units.measuring_unit_id')
            ->where('wod_capture_exercises.is_active', '=', 1)
            ->where('wod_capture.user_id', '=', $user->getAuthIdentifier())
            ->when($wod, function ($query) use ($wod) {
                $query->where('wod_capture.wod_id', '=', $wod->getKey());
            })
            ->when($exercise, function ($query) use ($exercise) {
                $query->where('wod_capture_exercises.exercise_id', '=', $exercise->getKey());
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('exercise.exercise_name', 'like', '%'.$search.'%')
                        ->orWhere('exercise.exercise_desc', 'like', '%'.$search.'%');
                });
            })
            ->when(! empty($relations), function ($query) use ($relations) {
                $query->with($relations);
            })
            ->select('wod_capture_exercises.*')
            ->addSelect([
                // 'wod_capture_exercises.wod_capture_exercise_id',
                // 'wod_capture.wod_capture_id',
                'exercise.exercise_id',
                'wod_capture.user_id',
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
                // 'wod_capture_exercises.is_rx',
                // 'wod_capture_exercises.score',
                'wod_capture.dt_added',
                DB::raw("'wod_excercise' as type"),
                // 'wod_capture_exercises.note',
            ])
            ->get();
    }

    public function getWodCaptureExercisesByWodCapture(WodCapture $wodCapture): Collection|array
    {
        return WodCaptureExercise::query()
            ->from('wod_capture', 'wc')
            ->join('wod_capture_exercises as wce', 'wce.wod_capture_id', '=', 'wc.wod_capture_id')
            ->join('wods', 'wc.wod_id', '=', 'wods.wod_id')
            ->where('wc.wod_capture_id', $wodCapture->getKey())
            ->where('wce.is_active', '=', true)
            ->orderByDesc('wods.wod_date')
            ->get();
    }
}
