<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CSVRule implements ValidationRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value?->getMimeType(), [
            'text/csv',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vdn.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])
        ) {
            $fail('Please provide valid CSV file.');
        }
    }
}
