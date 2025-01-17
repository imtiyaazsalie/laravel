<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Wod;
use App\Models\WodCapture;
use App\Models\WodCaptureExercise;
use App\Models\WodExercise;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class WODExerciseService
{
    private ExerciseService $exercise;

    private WODExercisePrefixService $wodExercisePrefix;

    public function __construct()
    {
        $this->exercise = (new ExerciseService());
        $this->wodExercisePrefix = (new WODExercisePrefixService());
    }

    public function deactivateAllActiveWodExercises(Wod $wod)
    {
        foreach ($wod->exercises()->get() as $activeWodExercise) {

            $activeWodExercise->update([
                'is_active' => false,
            ]);

            $activeWodExercisePrefixes = $activeWodExercise->prefixes()->get();

            foreach ($activeWodExercisePrefixes as $activeWodExercisePrefix) {

                $activeWodExercisePrefix->update([
                    'is_active' => false,
                ]);

            }
        }
    }

    public function updateOrCreateWodExercise($exerciseItem, Wod $wod)
    {
        $id = Arr::get($exerciseItem, 'id');
        $benchmarkId = Arr::get($exerciseItem, 'benchmark_id');
        $measuringUnitId = Arr::get($exerciseItem, 'measuring_unit_id');

        if ($id && $exercise = Exercise::find($id)->first()) {

            if (($exercise->isBenchmark() && $exercise->getKey() !== $benchmarkId)
                || ($exercise->isNotBenchmark() && $exercise->measuring_unit_id !== $measuringUnitId)
            ) {
                $this->storeWodExercise($exerciseItem, $wod);
            } else {

                $wodExercise = WodExercise::where('wod_id', '=', $wod->getKey())
                    ->active()
                    ->where('exercise_id', '=', $exercise->getKey())
                    ->first();

                $this->updateWodExercise($exerciseItem, $wodExercise);
            }

            return;
        }

        $this->storeWodExercise($exerciseItem, $wod);
    }

    public function updateWodExercise($exercisesItem, WodExercise $wodExercise)
    {
        $wodExercise->fill([
            'order' => Arr::get($exercisesItem, 'order'),
            'prefix' => Arr::get($exercisesItem, 'prefix'),
        ])->save();
    }

    public function storeWodExercise($exercisesItem, Wod $wod)
    {
        if ($exerciseId = Arr::get($exercisesItem, 'benchmark_id')) {
            $exercise = Exercise::findOrFail($exerciseId);
        } else {
            $exercise = $this->exercise->store([
                'tenant_id' => $wod->tenant_id,
                'measuring_unit_id' => Arr::get($exercisesItem, 'measure_id', 1),
                'exercise_name' => Arr::get($exercisesItem, 'name'),
                'exercise_desc' => Arr::get($exercisesItem, 'description'),
                'resource_url' => Arr::get($exercisesItem, 'resource_url'),
                'is_active' => true,
            ]);
        }

        $wodExercise = $this->store([
            'wod_id' => $wod->getKey(),
            'exercise_id' => $exercise->getKey(),
            'wte_order' => Arr::get($exercisesItem, 'order'),
            'is_active' => true,
        ]);

        if (isset($exercisesItem['prefix'])) {
            $this->wodExercisePrefix->store([
                'prefix' => $exercisesItem['prefix'],
                'wod_to_exercise_id' => $wodExercise->getKey(),
                'is_active' => true,
            ]);
        }
    }

    public function store($data)
    {
        $wodExercise = new WodExercise();
        $wodExercise->fill($data);
        $wodExercise->is_active = true;
        $wodExercise->save();

        return $wodExercise->loadMissing('prefixes');
    }

    public function getWodCaptureExercisesForExercise(Exercise $exercise, ?User $user = null, ?WodCapture $wodCaptureExclusion = null): Collection|array
    {
        $qb = WodCaptureExercise::query()
            ->from('wod_capture_exercises', 'wce')
            ->join('wod_capture as wc', 'wc.wod_capture_id', '=', 'wce.wod_capture_id')
            ->join('wods as w', 'w.wod_id', '=', 'wc.wod_id')
            ->where('wce.exercise_id', $exercise->getKey())
            ->where('wce.is_active', true)
            ->orderByDesc('w.wod_date');

        if ($user instanceof User) {
            $qb->where('wc.user_id', $user->getKey());
        }

        return $qb->get();
    }
}
