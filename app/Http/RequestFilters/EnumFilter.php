<?php

namespace App\Http\RequestFilters;

use App\Exceptions\InvalidEnumValueException;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\Filters\Filter;

class EnumFilter implements Filter
{
    public function __construct(
        public $enum
    ) {
        if (! enum_exists($enum)) {
            throw new Exception('ENUM filter requires valid ENUM.');
        }
    }

    public function __invoke(Builder $query, $value, string $property)
    {
        if (! (is_string($value) || is_int($value) || is_array($value))) {
            throw new InvalidFilterValue();
        }

        $value = (array) $value;

        $values = array_map(fn ($case) => $case->value, $this->enum::cases());

        $missing = array_diff($value, $values);

        if (! empty($missing)) {
            throw new InvalidEnumValueException($property);
        }

        $query->when(
            count($value) === 1,
            function () use ($query, $property, $value) {
                $query->where($query->from.'.'.$property, $value);
            },
            function () use ($query, $property, $value) {
                $query->whereIn($query->from.'.'.$property, $value);
            }
        );
    }
}
