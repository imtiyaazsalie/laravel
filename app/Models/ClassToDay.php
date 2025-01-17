<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassToDay extends Model
{
    protected $table = 'class_to_days';

    protected $primaryKey = 'class_to_day_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];
}
