<?php

namespace App\Models;

use App\Enums\InvoicePaymentType;
use App\Traits\HasTags;
use App\Traits\MutatesUserToFacilityId;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserInvoicePayment extends Model
{
    use HasTags, MutatesUserToFacilityId, Paginatable, RecordUserOnCreateAndUpdate, SoftDeletesBoolean;

    protected $table = 'finance_payments';

    protected $primaryKey = 'payment_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public const UPDATED_BY_ID = null;

    protected $guarded = [];

    protected $casts = [
        'type' => InvoicePaymentType::class,
        'amount' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'invoice_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'invoice_id');
    }

    public function userLocation(): HasOne
    {
        return $this->hasOne(LocationUser::class, 'user_to_facility_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
