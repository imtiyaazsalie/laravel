<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DebitDayDate extends Model
{
    use HasFactory;

    protected $table = 'debit_day_dates';

    protected $primaryKey = 'debit_day_date_id';

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'debit_day_date' => 'datetime',
        'is_active' => 'integer',
    ];

    /**
     * Mutate debit_day_date to date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_day_date,
            set: fn (mixed $value) => ['debit_day_date' => $value]
        );
    }

    public function debitDay(): BelongsTo
    {
        return $this->belongsTo(DebitDay::class, 'debit_day_id');
    }

    public function scopeAfter(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('debit_day_date', '>', $date);
    }

    public function scopeDebitDayDateBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('debit_day_date', [Carbon::parse($from), Carbon::parse($to)]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
