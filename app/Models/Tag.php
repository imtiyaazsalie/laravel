<?php

namespace App\Models;

use App\Enums\PaymentProcessorTag;
use App\Enums\TagType;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory, RecordUserOnCreateAndUpdate, SoftDeletes;

    protected $table = 'tags';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = true;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => TagType::class,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePaymentTag(Builder $query, PaymentProcessorTag $paymentProcessor): Builder
    {
        return $query->where('type', TagType::PAYMENT->value)
            ->whereNull('owner_model_id')
            ->whereNull('owner_model')
            ->where('name', $paymentProcessor->value);
    }

    public function scopeOwnedByGym(Builder $query, string|int $boxId): Builder
    {
        if (request()->input('filter.type') == 'location') {

            return $query->where(function ($query) use ($boxId) {
                $query->where('owner_model', (new Location())->getTable())
                    ->whereIn('owner_model_id', Tenant::find($boxId)->locations->pluck('box_facility_id')->toArray());
            })->orWhereNull('owner_model');
        }

        return $query->where(function ($query) use ($boxId) {
            $query->where('owner_model', (new Tenant())->getTable())
                ->where('owner_model_id', $boxId);
        })->orWhereNull('owner_model');
    }

    public function scopeOwnedByLocation(Builder $query, string|int $locationId): Builder
    {
        return $query->where(function ($query) use ($locationId) {
            $query->where('owner_model', (new Location())->getTable())
                ->where('owner_model_id', $locationId);
        });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('owner_model')
            ->whereNull('owner_model_id');
    }

    /**
     * Ownership scope.
     */
    public function scopeOwnedBy(Builder $query, string|int $boxId, string|int|null $locationId): Builder
    {
        return $query->global()
            ->orWhere(function ($query) use ($boxId) {
                $query->ownedByGym($boxId);
            })
            ->when($locationId, function ($query) use ($locationId) {
                $query->orWhere(function ($query) use ($locationId) {
                    $query->ownedByLocation($locationId);
                });
            });

    }

    public function isGlobal(): bool
    {
        return is_null($this->owner_model_id) && is_null($this->owner_model);
    }

    public function isOwnedByGym(): bool
    {
        return $this->owner_model === (new Tenant())->getTable();
    }

    public function isOwnedByLocation(): bool
    {
        return $this->owner_model === (new Location())->getTable();
    }
}
