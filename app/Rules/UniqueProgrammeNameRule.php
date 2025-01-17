<?php

namespace App\Rules;

use App\Models\Programme;
use Illuminate\Contracts\Validation\Rule;

class UniqueProgrammeNameRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public string|int $tenantId,
        public ?Programme $exclude = null
    ) {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (! is_string($value)) {
            return false;
        }

        $query = Programme::query()
            ->whereName($value)
            ->where('box_id', '=', $this->tenantId)
            ->when($this->exclude, function ($query) {
                $query->whereNot('id', $this->exclude->getKey());
            });

        return $query->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Programme name must be unique.';
    }
}
