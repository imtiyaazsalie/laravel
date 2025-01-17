<?php

namespace App\Models;

use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LocationPayment extends Model
{
    use HasFactory, IsOwnedByLocation, Paginatable;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'finance_facility_payments';

    protected $primaryKey = 'box_payment_id';

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LocationInvoice::class, 'facility_invoice_id');
    }

    public function scopePaidBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('date_time', [Carbon::parse($from), Carbon::parse($to)]);
    }

    public function scopeSearch()
    {
    }

    /**
     * Mutate facility_invoice_id to location_invoice_id
     */
    protected function locationInvoiceId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->facility_invoice_id,
            set: fn (mixed $value) => ['facility_invoice_id' => $value]
        );
    }
}
