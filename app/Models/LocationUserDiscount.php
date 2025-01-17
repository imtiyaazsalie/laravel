<?php

namespace App\Models;

use App\Traits\MutatesUserToFacilityId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationUserDiscount extends Model
{
    use HasFactory, MutatesUserToFacilityId;

    protected $table = 'facility_membership_discounts';

    protected $primaryKey = 'facility_membership_discount_id';

    public $timestamps = false;

    protected $guarded = [];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(FinanceDiscount::class, 'discount_id', 'discount_id');
    }

    public function userLocation(): BelongsTo
    {
        return $this->belongsTo(LocationUser::class, 'user_to_facility_id');
    }

    public function disable(): bool
    {
        return $this->update([
            'ending_on' => now(),
            'status' => 'deactivated',
        ]);
    }
}
