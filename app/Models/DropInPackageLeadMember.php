<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropInPackageLeadMember extends Model
{
    use HasFactory;

    protected $table = 'drop_in_packages_to_lead_members';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'sessions_purchased' => 'integer',
        'sessions_remaining' => 'integer',
        'is_confirmation_sent' => 'integer',
    ];

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(LeadMember::class, 'lead_member_id');
    }

    public function dropInPackage(): BelongsTo
    {
        return $this->belongsTo(DropInPackage::class, 'drop_in_package_id');
    }

    public function userInvoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'invoice_id');
    }
}
