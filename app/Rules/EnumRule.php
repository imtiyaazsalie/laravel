<?php

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

class EnumRule implements ValidationRule
{
    public function __construct(
        public $enum
    ) {
        if (! enum_exists($enum)) {
            throw new Exception('ENUM rule requires valid ENUM.');
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! (is_string($value) || is_int($value) || is_array($value))) {
            $fail('The :attribute must be a valid string, int or array<string|int>.');

            return;
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $value = (array) $value;

        $values = array_map(fn ($case) => $case->value, $this->enum::cases());

        $missing = array_diff($value, $values);

        if (! empty($missing)) {
            $fail('The :attribute must contain valid '.str(class_basename($this->enum))->snake()->value().' ENUM value.');

            return;
        }
    }
}
