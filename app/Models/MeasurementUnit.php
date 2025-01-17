<?php

namespace App\Models;

use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    use HasFactory, Paginatable;

    protected $table = 'measuring_units';

    protected $primaryKey = 'measuring_unit_id';

    public $timestamps = false;

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $guarded = [];

    /**
     * Mutate measuring_unit_short to unit
     */
    protected function unit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->measuring_unit_short,
            set: fn (mixed $value) => ['measuring_unit_short' => $value]
        );
    }

    /**
     * Mutate measuring_unit_desc to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->measuring_unit_desc,
            set: fn (mixed $value) => ['measuring_unit_desc' => $value]
        );
    }

    /**
     * Mutate measuring_unit_format to format
     */
    protected function format(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->measuring_unit_format,
            set: fn (mixed $value) => ['measuring_unit_format' => $value]
        );
    }

    public function toggleStatus()
    {
        $this->update(['is_active' => ! $this->is_active]);
    }
}
