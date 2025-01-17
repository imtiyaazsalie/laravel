<?php

namespace App\Models;

use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseCategory extends Model
{
    use HasFactory, Paginatable;

    protected $table = 'exercise_category';

    protected $primaryKey = 'exercise_category_id';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'is_active' => 'integer',
        'is_benchmark' => 'integer',
    ];

    protected $attributes = [
        'is_active' => 1,
    ];

    /**
     * Mutate exercise_category_desc to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exercise_category_desc,
            set: fn (mixed $value) => ['exercise_category_desc' => $value]
        );
    }
}
