<?php

namespace App\Rules;

use App\Models\WodCaptureExercise;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class UniqueWodCaptureExerciseRule implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public string|int|null $except = null
    ) {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $wodId = Arr::get($this->data, 'wod_id')) {
            $fail('Wod ID is required for unique wod capture check.');

            return;
        }

        if (! $userId = Arr::get($this->data, 'user_id')) {
            $fail('User ID is required for unique wod capture check.');

            return;
        }

        if (! $exerciseId = Arr::get($this->data, 'exercise_id')) {
            $fail('User ID is required for unique wod capture check.');

            return;
        }

        if (WodCaptureExercise::query()
            ->joinRelationship('capture')
            ->when($this->except, function ($query) {
                $query->whereNot('wod_capture_exercises.wod_capture_id', $this->except);
            })
            ->where('wod_capture_exercises.is_active', true)
            ->where('wod_capture.user_id', $userId)
            ->where('wod_capture.wod_id', $wodId)
            ->where('wod_capture_exercises.exercise_id', $exerciseId)
            ->exists()) {
            $fail('A wod capture exercise already exists for this user and exercise.');
        }
    }
}
