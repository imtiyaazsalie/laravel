<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    use HasFactory;

    protected $table = 'pos_sale_items';

    protected $primaryKey = 'sale_item_id';

    protected $guarded = [];

    public $timestamps = false;

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(PosStockItem::class, 'stock_item_id')->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'sale_id');
    }

    public function sessionsReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sessions_released_by_id');
    }

    protected function taxTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price_including_vat - $this->price_excluding_vat
        );
    }

    protected function priceExcludingVat(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity * $this->stockItem->selling_price_excluding_vat
        );
    }

    protected function priceIncludingVat(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity * $this->stockItem->selling_price
        );
    }
}
