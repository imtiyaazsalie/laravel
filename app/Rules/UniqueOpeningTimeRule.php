<?php

namespace App\Rules;

use App\Models\OperatingHour;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class UniqueOpeningTimeRule implements DataAwareRule, ValidationRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    public function __construct(
        public ?OperatingHour $ignore = null
    ) {
        //
    }

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
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->ignore) {
            $locationId = $this->ignore->location_id;
        } else {
            if (! $locationId = Arr::get($this->data, 'location_id')) {
                $fail('Location ID is required to check unique opening times.');

                return;
            }
        }

        if (! $day = Arr::get($this->data, 'day')) {
            $fail('Day is required to check unique opening times.');

            return;
        }

        if (OperatingHour::query()
            ->where('day', $day)
            ->where('opening_time', $value)
            ->where('location_id', $locationId)
            ->when($this->ignore, function (Builder $query) {
                $query->whereKeyNot($this->ignore->getKey());
            })
            ->exists()
        ) {
            $fail('Opening time must be unique to location and day.');

            return;
        }
    }
}
