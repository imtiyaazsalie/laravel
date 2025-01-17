<?php

namespace App\Models;

use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
    use HasFactory, Paginatable;

    protected $table = 'timezones';

    protected $primaryKey = 'timezone_id';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_daylight_saving' => 'integer',
    ];

    /**
     * Mutate abbr to abbreviation
     */
    protected function abbreviation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->abbr,
            set: fn (mixed $value) => ['abbr' => $value]
        );
    }
}
