<?php

namespace App\Rules;

use App\Models\FinanceDiscount;
use App\Models\Scopes\OwnedByTenantScope;
use Illuminate\Contracts\Validation\Rule;

class DiscountRule implements Rule
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
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $query = FinanceDiscount::query()
            ->whereKey($value);

        if ($this->tenantId) {
            $query = $query->where('box_id', $this->tenantId)
                ->withoutGlobalScopes([OwnedByTenantScope::class]);
        }

        return $query->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Discount ID not found.';
    }
}
