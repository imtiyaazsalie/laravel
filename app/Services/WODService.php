<?php

namespace App\Services;

use App\Models\Programme;
use App\Models\Tenant;
use App\Models\Wod;
use App\Models\WodCaptureComments;
use App\Models\WodCaptureExercise;
use App\Models\WodCaptureLikes;
use App\Models\WodExercisePrefix;

class WODService
{
    public function store($data)
    {
        return Wod::create($data);
    }

    public function delete(Wod $wod)
    {
        $exercises = $wod->allExercises()->get();

        if ($exercises->isNotEmpty()) {
            WodExercisePrefix::query()
                ->whereIn('wod_to_exercise_id', $exercises->modelKeys())
                ->delete();

            $exercises->toQuery()->delete();
        }

        $captures = $wod->captures()->get();

        if ($captures->isNotEmpty()) {

            WodCaptureExercise::query()
                ->whereIn('wod_capture_id', $captures->modelKeys())
                ->delete();

            WodCaptureLikes::query()
                ->whereIn('wod_capture_id', $captures->modelKeys())
                ->delete();

            WodCaptureComments::query()
                ->whereIn('wod_capture_id', $captures->modelKeys())
                ->delete();

            $captures->toQuery()->delete();
        }

        $wod->delete();
    }

    public function update(Wod $wod, $data)
    {
        return $wod->update($data);
    }

    public function getWodForBoxAndDateAndProgramme(Tenant $box, \DateTime $date, Programme $programme): ?object
    {
        return Wod::query()
            ->where('box_id', $box->getKey())
            ->where('wod_date', $date)
            ->where('programme_id', $programme->getKey())
            ->first();
    }
}
