<?php

namespace App\Models;

use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Kirschbaum\PowerJoins\PowerJoins;

class PosStockItem extends Model
{
    use HasFactory, IsOwnedByLocation, Paginatable, PowerJoins, SoftDeletesBoolean;

    protected $table = 'pos_stock_items';

    protected $primaryKey = 'stock_item_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * Mutate selling_price to price
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->selling_price,
            set: fn (mixed $value) => ['selling_price' => $value]
        );
    }

    /**
     * Mutate cost_price to cost
     */
    protected function cost(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cost_price,
            set: fn (mixed $value) => ['cost_price' => $value]
        );
    }

    /**
     * Mutate stock_level to stock
     */
    protected function stock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stock_level,
            set: fn (mixed $value) => ['stock_level' => $value]
        );
    }

    /**
     * Mutate image_url to image
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_url,
            set: fn (mixed $value) => ['image_url' => $value]
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url ? Storage::disk('public')->url($this->image_url) : null;
    }

    protected function sellingPriceExcludingVat(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->vat) {
                    return $this->selling_price;
                }

                $divisor = 1 + ($this->vat / 100);

                return $this->selling_price / $divisor;
            }
        );
    }

    protected function sellingPriceIncludingVat(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->vat) {
                    return $this->selling_price;
                }

                return $this->selling_price * ((100 + $this->vat) / 100);
            }
        );
    }
}
