<?php

namespace App\Rules;

use App\Models\PosStockItem;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

class StockItemRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public string|int|null $locationId = null
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
        if (empty($value)) {
            $fail('The :attribute field is required.');

            return;
        }

        $exists = PosStockItem::query()
            ->when(
                $this->locationId,
                fn (Builder $query) => $query->where('box_facility_id', $this->locationId)
            )
            ->whereKey($value)
            ->exists();

        if (! $exists) {
            $fail('The :attribute field is invalid.');

            return;
        }
    }
}
