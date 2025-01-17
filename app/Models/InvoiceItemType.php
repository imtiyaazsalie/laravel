<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemType extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable;

    protected $table = 'finances_invoice_item_types';

    protected $primaryKey = 'invoice_item_type_id';

    public $timestamps = false;

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }
}
