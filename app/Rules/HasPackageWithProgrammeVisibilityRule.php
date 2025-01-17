<?php

namespace App\Rules;

use App\Models\User;
use App\Traits\ValidatesIds;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HasPackageWithProgrammeVisibilityRule implements ValidationRule
{
    use ValidatesIds;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public ?User $user = null
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
            $fail('The :attribute must be a valid string, integer or array<string|int>');

            return;
        }

        if (! auth()->user()->hasPackageWithProgrammeVisibility($ids)) {
            $fail('Member has no package with programme visibility');
        }
    }
}
