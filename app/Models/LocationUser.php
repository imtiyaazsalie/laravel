<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\IsOwnedByLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Kirschbaum\PowerJoins\PowerJoins;
use Reedware\LaravelCompositeRelations\CompositeHasMany;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;

class LocationUser extends Model
{
    use BelongsToTenant, HasCompositeRelations, HasFactory, IsOwnedByLocation, PowerJoins;

    protected $table = 'user_to_facility';

    protected $primaryKey = 'user_to_facility_id';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->location->box_id,
        );
    }

    /**
     * Mutate effective_date to start_date
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->effective_date,
            set: fn (mixed $value) => ['effective_date' => $value]
        );
    }

    protected function locationId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_id,
            set: fn (mixed $value) => ['box_facility_id' => $value]
        );
    }

    public function tenant(): HasOneThrough
    {
        return $this->hasOneThrough(Tenant::class, Location::class, 'box_facility_id', 'box_id', 'box_facility_id', 'box_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(LeadMember::class, 'lead_member_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(UserInvoice::class, 'user_to_facility_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('user_to_facility.end_date', '>', today()->toDateString());
    }

    public function contracts(): CompositeHasMany
    {
        return $this->compositeHasMany(UserContract::class, ['box_facility_id', 'user_id'], ['box_facility_id', 'user_id']);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(LocationUserDiscount::class, 'user_to_facility_id');
    }

    public function activeDiscounts(): HasMany
    {
        return $this->hasMany(LocationUserDiscount::class, 'user_to_facility_id')
            ->where('status', 'active')
            ->where('starting_on', '<=', today()->toDateString())
            ->where(function ($query) {
                $query->where('ending_on', '>=', today()->toDateString())
                    ->orWhereNull('ending_on');
            });
    }
}
