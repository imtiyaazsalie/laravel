<?php

namespace App\Rules;

use App\Models\Location;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Symfony\Component\Uid\Uuid;

class LocationAttendanceCodeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $location = Location::where('attendance_code', Uuid::fromString($value)->toBinary())->count();

        if ($location == 0) {
            $fail('The :attribute is invalid.');
        }

    }
}
