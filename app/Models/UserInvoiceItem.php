<?php

namespace App\Models;

use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvoiceItem extends Model
{
    use RecordUserOnCreateAndUpdate, SoftDeletesBoolean;

    protected $table = 'finance_invoice_items';

    protected $primaryKey = 'invoice_item_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public const UPDATED_BY_ID = null;

    protected $guarded = [];

    protected $attributes = [
        'quantity' => 1,
        'deleted' => false,
    ];

    /**
     * Mutate unitPrice to unit_price
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->unitPrice,
            set: fn (mixed $value) => ['unitPrice' => $value]
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_to_package_id', 'user_to_package_id');
    }

    public function exVatAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => bcsub($this->amount, $this->vat_amount, 2)
        );
    }

    public function vatAmount(): Attribute
    {
        $vat = $this->vat;

        if ($this->invoice?->invoice_location?->vat_percentage && is_null($this->vat)) {
            $vat = $this->invoice?->invoice_location?->vat_percentage;
        } elseif ($this->invoice?->invoice_location?->vat_percentage && ! is_null($this->vat)) {
            $vat = $this->vat;
        }

        return Attribute::make(
            get: fn () => bcsub($this->amount, bcdiv(($this->amount * 100), (100 + $vat), 2), 2)
        );
    }

    public function getAmountInCentsAttribute(): int
    {
        return (int) bcmul($this->amount, 100);
    }
}
