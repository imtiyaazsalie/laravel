<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class IdNumberRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (! is_string($value)) {
            $fail('Please ensure that your RSA ID/Passport number is correct wsd');

            return;
        }

        if (strlen($value) > 13 || strlen($value) < 6 || preg_match('/[^a-z\-0-9]/i', $value)) {
            $fail('Please ensure that your RSA ID/Passport number is correct');

            return;
        }

        if (strlen($value) === 13 && preg_match('/[a-z]/i', $value)) {
            $fail('Please ensure that your RSA ID number is correct');

            return;
        }
    }
}
