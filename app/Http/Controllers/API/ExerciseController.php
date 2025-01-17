<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exercise\ListExercisesRequest;
use App\Http\Requests\Exercise\ShowExerciseRequest;
use App\Http\Requests\Exercise\StoreExerciseRequest;
use App\Http\Requests\Exercise\UpdateExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Services\ExerciseService;
use Knuckles\Scribe\Attributes\QueryParam;

class ExerciseController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    #[QueryParam('filter[exercise_category_id]', 'integer', required: false)]
    #[QueryParam('filter[search]', 'string', required: false)]
    #[QueryParam('filter[type]', 'enum', required: false)]
    public function list(ListExercisesRequest $request)
    {
        return ExerciseResource::collection((new ExerciseService())->listByRequest());
    }

    public function show(ShowExerciseRequest $request, Exercise $exercise)
    {
        return new ExerciseResource($exercise);
    }

    public function store(StoreExerciseRequest $request)
    {
        $exercise = new Exercise();

        $exercise->fill($request->safe()->all());
        $exercise->save();

        return new ExerciseResource($exercise);
    }

    public function update(UpdateExerciseRequest $request, Exercise $exercise)
    {
        $exercise->update($request->safe()->all());

        return new ExerciseResource($exercise);
    }
}
