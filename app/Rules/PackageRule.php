<?php

namespace App\Rules;

use App\Models\Package;
use Illuminate\Contracts\Validation\Rule;

class PackageRule implements Rule
{
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
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return false;
        }

        return Package::query()
            ->whereKey($value)
            ->when($this->tenantId, function ($query) {
                $query->where('box_id', $this->tenantId);
            })->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Package ID not found.';
    }
}
