<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property string $name
 * @property string $universal_code
 * @property string $import_code
 */
class Bank extends Model
{
    use HasFactory;

    protected $table = 'banks';

    protected $primaryKey = 'bank_id';

    protected $fillable = ['*'];

    public $timestamps = false;

    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'country_id', 'country_id');
    }

    /**
     * Mutate bank_name to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bank_name,
            set: fn (mixed $value) => ['bank_name' => $value]
        );
    }

    protected function universalCodeFormattedForNetcashBatch(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::padLeft(trim($this->universal_code), 6, 0),
        );
    }
}
