<?php

namespace App\Models;

use App\Enums\LeadMemberStatus;
use App\Models\Scopes\BooleanTrashScope;
use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class LeadMember extends Model
{
    use HasFactory, IsOwnedByLocation, Paginatable, RecordUserOnCreateAndUpdate;

    protected $table = 'lead_members';

    protected $primaryKey = 'member_id';

    public $timestamps = true;

    public const CREATED_BY_ID = 'captured_by_id';

    public const UPDATED_BY_ID = null;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'converted_on' => 'datetime',
        'status' => LeadMemberStatus::class,
        'deleted' => 'integer',
    ];

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function waiver(): BelongsTo
    {
        return $this->belongsTo(LeadWaivers::class, 'waiver_id');
    }

    public function classBookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'lead_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes([BooleanTrashScope::class]);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(UserInvoice::class, 'lead_member_id');
    }

    public function crmRecipient(): MorphOne
    {
        return $this->morphOne(MailerRecipient::class, 'crmRecipient');
    }

    public function getNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getEmailAttribute()
    {
        return $this->email_address;
    }
}
