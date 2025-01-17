<?php

namespace App\Models;

use App\Enums\PosStatus;
use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use HasFactory, IsOwnedByLocation, Paginatable, SoftDeletesBoolean;

    protected $table = 'pos_sales';

    protected $primaryKey = 'sale_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'status' => PosStatus::class,
        'deleted' => 'integer',
        'otp_verified' => 'integer',
        'sold_on' => 'date',
    ];

    protected $attributes = [
        'status' => PosStatus::OPEN,
    ];

    public function saleItems(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'sale_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(PosStockItem::class, 'stock_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'invoice_id');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id')
            ->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')
            ->withTrashed();
    }

    public function getTotalCostPriceOfCart($stockItem = null): float|int
    {
        $total = 0;

        if ($stockItem) {
            /** @var PosSaleItem $saleItem */
            foreach ($this->saleItems as $saleItem) {
                if ($stockItem == $saleItem->stockItem) {
                    $total += $saleItem->stockItem->cost_price * $saleItem->quantity;
                }
            }
        } else {
            /** @var PosSaleItem $saleItem */
            foreach ($this->saleItems as $saleItem) {
                $total += $saleItem->stockItem()->withTrashed()->first()->cost_price * $saleItem->quantity;
            }
        }

        return $total;
    }

    public function getTotalOfCart($stockItem = null)
    {
        $total = 0;

        if ($stockItem) {
            /** @var PosSaleItem $saleItem */
            foreach ($this->saleItems as $saleItem) {
                if ($stockItem == $this->saleItems) {
                    $total += $saleItem->stockItem->selling_price * $saleItem->quantity;
                }
            }
        } else {
            /** @var PosSaleItem $saleItem */
            foreach ($this->saleItems as $saleItem) {
                $total += $saleItem->stockItem()->withTrashed()->first()->selling_price * $saleItem->quantity;
            }

            if (! empty($this->discount_amount)) {
                $total = $total - $this->discount_amount;
            }
        }

        return $total;
    }
}
