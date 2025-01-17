<?php

namespace App\Rules;

use App\Models\Exercise;
use App\Traits\ValidatesIds;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExerciseRule implements ValidationRule
{
    use ValidatesIds;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public string|int|null $tenantId = null
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
        if (! $ids = $this->validateIds($value)) {
            $fail('Exercise ID(s) not valid.');

            return;
        }

        $query = Exercise::query()
            ->whereIn('exercise_id', $ids)
            ->when(
                $this->tenantId,
                function ($query) {
                    $query->where(function ($query) {
                        $query->whereNull('box_id')
                            ->orWhere('box_id', $this->tenantId);
                    });
                }
            );

        if ($query->count() !== count($ids)) {
            $fail('Exercise ID(s) not found.');
        }
    }
}
