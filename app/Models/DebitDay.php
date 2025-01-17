<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebitDay extends Model
{
    use HasFactory;

    protected $table = 'debit_days';

    protected $primaryKey = 'debit_day_id';

    public $timestamps = false;

    public function debitDayDates(): HasMany
    {
        return $this->hasMany(DebitDayDate::class, 'debit_day_id');
    }

    /**
     * Mutate debit_day_descr to name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_day_descr,
            set: fn (mixed $value) => ['debit_day_descr' => $value]
        );
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('isActive', function (Builder $builder) {
            $builder->where('is_active', true);
        });
    }

    public function getDate(?Carbon $monthAndYear): Carbon
    {
        if (! $monthAndYear) {
            $monthAndYear = today()->startOfMonth()->toDateString();
        }

        if ((int) $this->import_code === 31) {
            return Carbon::parse($monthAndYear->format('Y-m-').$monthAndYear->lastOfMonth()->format('d'));
        }

        return Carbon::parse($monthAndYear->format('Y-m-').(string) $this->import_code);
    }
}
