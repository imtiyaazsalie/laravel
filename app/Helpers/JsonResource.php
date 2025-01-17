<?php

namespace App\Helpers;

use Illuminate\Http\Resources\Json\JsonResource as IlluminateJsonResource;

class JsonResource extends IlluminateJsonResource
{
    public static function collection($resource)
    {
        return tap(new AnonymousResourceCollection($resource, static::class), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }
}
