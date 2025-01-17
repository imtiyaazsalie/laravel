<?php

namespace App\Rules;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DatesBetweenRule implements ValidationRule
{
    public bool $implicit = false;

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute value must be a string.');

            return;
        }

        $value = str($value);

        if (! $value->contains(',')) {
            $fail('The :attribute value must be a string of two comma separated dates.');

            return;
        }

        $dates = $value->explode(',');

        if ($dates->count() !== 2) {
            $fail('The :attribute value must be a string of two comma separated dates.');

            return;
        }

        $dates->each(function ($date) use (&$fail) {
            try {
                if (Carbon::parse($date)->toDateString() !== $date) {
                    $fail('The :attribute values must be valid dates.');
                }
            } catch (InvalidFormatException) {
                $fail('The :attribute value must contain valid comma separated date strings.');
            }
        });

    }
}
