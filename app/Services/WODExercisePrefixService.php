<?php

namespace App\Services;

use App\Models\WodExercisePrefix;

class WODExercisePrefixService
{
    public function store($data)
    {
        $wodExercisePrefix = new WodExercisePrefix();
        $wodExercisePrefix->fill($data);
        $wodExercisePrefix->save();

        return $wodExercisePrefix;
    }
}
