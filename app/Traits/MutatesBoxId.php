<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;

trait MutatesBoxId
{
    /**
     * Mutate box_id to tenant_id
     */
    protected function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => Arr::get($attributes, 'box_id'),
            set: fn (mixed $value) => ['box_id' => $value]
        );
    }
}
