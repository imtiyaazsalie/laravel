<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ClassDay extends Model
{
    protected $table = 'class_days';

    protected $primaryKey = 'class_day_id';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Mutate class_day_descr to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->class_day_descr,
            set: fn (mixed $value) => ['class_day_descr' => $value]
        );
    }
}
