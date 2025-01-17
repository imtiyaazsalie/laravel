<?php

namespace App\Rules;

use App\Models\Wod;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class UniqueWodDateRule implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public ?Wod $except = null
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
        if (! $programmeId = Arr::get($this->data, 'programme_id')) {
            $fail('Programme ID field is required to validate unique WODs.');

            return;
        }

        if (Wod::query()
            ->where('wod_date', $value)
            ->where('programme_id', $programmeId)
            ->when($this->except, function ($query) {
                $query->whereNot('wod_id', $this->except->getKey());
            })
            ->exists()) {
            $fail('A workout already exists for this programme and date.');
        }
    }
}
