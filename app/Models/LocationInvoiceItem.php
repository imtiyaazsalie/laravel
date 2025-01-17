<?php

namespace App\Models;

use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class LocationInvoiceItem extends Model
{
    use RecordUserOnCreateAndUpdate;

    protected $table = 'finance_facility_invoice_items';

    protected $primaryKey = 'facility_invoice_item_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public const UPDATED_BY_ID = null;

    protected $attributes = [
        'deleted' => 0,
    ];

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
