<?php

namespace App\Models;

use App\Traits\IsOwnedByLocation;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LocationInvoice extends Model
{
    use IsOwnedByLocation, RecordUserOnCreateAndUpdate;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public const UPDATED_BY_ID = null;

    public $timestamps = true;

    protected $table = 'finance_facility_invoices';

    protected $primaryKey = 'facility_invoice_id';

    protected $guarded = [];

    protected $appends = [
        'outstanding_amount',
    ];

    protected $attributes = [
        'deleted' => 0,
    ];

    protected $casts = [
        'due_on' => 'date',
        'sent_on' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function getAmountInCentsAttribute(): int
    {
        return (int) bcmul($this->amount, 100);
    }

    public function randAmountInCents(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) bcmul($this->amount_in_rands, 100),
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(LocationInvoiceItem::class, 'facility_invoice_id');
    }

    public function scopeDueOnBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('due_on', [Carbon::parse($from), Carbon::parse($to)]);
    }

    public function scopeSentStatus(Builder $query, $status): Builder
    {
        return match ($status) {
            'sent' => $query->whereNotNull('sent_on'),
            'unsent' => $query->whereNull('sent_on'),
            default => $query->where('sent_on', '=', null)
                ->orWhereDate('sent_on', '=', 'sent_on'),
        };
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->where('code', 'LIKE', "%$search%")
            ->orWhere('box_facility.box_facility_name', 'LIKE', "%$search%")
            ->orWhere('boxes.box_desc', 'LIKE', "%$search%");
    }

    public function getOutstandingAmountAttribute(): float|int
    {
        return $this->amount - $this->payments()->sum('amount');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LocationPayment::class, 'facility_invoice_id');
    }
}
