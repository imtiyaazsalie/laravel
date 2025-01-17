<?php

namespace App\Http\RequestFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Spatie\QueryBuilder\Filters\Filter;

class TagsFilter implements Filter
{
    public function __construct(
        public string $morphable,
        public string $type
    ) {
    }

    public function __invoke(Builder $query, $value, string $property)
    {
        if (! (is_string($value) || is_int($value) || is_array($value))) {
            throw new InvalidFilterValue();
        }

        $value = (array) $value;

        $query->join('taggables', function (JoinClause $join) use ($query) {
            $join->on($query->getModel()->getTable().'.'.$query->getModel()->getKeyName(), '=', 'taggables.morphable_id')
                ->where('taggables.morphable_model', '=', $this->morphable)
                ->whereNull('taggables.deleted_at');
        })
            ->whereIn('tag_id', $value)
            ->join('tags', function (JoinClause $join) {
                $join->on('tags.id', '=', 'taggables.tag_id')
                    ->where('tags.type', '=', $this->type)
                    ->whereNull('tags.deleted_at');
            });
    }
}
