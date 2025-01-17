<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PerPageRule implements Rule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(public int $min = 1, public int $max = 1000)
    {
    }

    /**
     * Determine if value is null or an integer between min and max values.
     */
    public function passes($attribute, $value): bool
    {
        if ($value == -1) {
            return true;
        }

        $value = (int) $value;

        if ($value >= $this->min && $value <= $this->max) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Per page must be a number between '.$this->min.' and '.$this->max;
    }
}
