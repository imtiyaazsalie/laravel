<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

class PriceRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(protected float $min = 0.00, protected float $max = 100000000.00)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalizedAmount = str_replace(',', '.', $value);
        Validator::make(['price' => $normalizedAmount], [
            'price' => 'required|numeric|between:'.(string) $this->min.'.,'.(string) $this->max,
        ])->validate();

        // if (! preg_match("^\d+.\d\d^", $value)) {
        //     $fail('The :attribute should be a string or float with format of 0.00');
        // }
    }
}
