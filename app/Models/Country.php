<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $primaryKey = 'country_id';

    protected $fillable = ['*'];

    public $timestamps = false;

    /**
     * Mutate country_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->country_name,
            set: fn (mixed $value) => ['country_name' => $value]
        );
    }

    /**
     * Mutate country_timezone to timezone
     */
    protected function timezone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->country_timezone,
            set: fn (mixed $value) => ['country_timezone' => $value]
        );
    }
}
