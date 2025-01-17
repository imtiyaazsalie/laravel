<?php

namespace App\Models;

use App\Traits\IsOwnedByLocation;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Injury extends Model
{
    use HasFactory, IsOwnedByLocation, IsOwnedByTenant, Paginatable, RecordUserOnCreateAndUpdate, SoftDeletesStatus;

    protected $table = 'injury_injuries';

    protected $primaryKey = 'injury_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    /**
     * Mutate created_for_id to user_id
     */
    protected function userId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_for_id,
            set: fn (mixed $value) => ['created_for_id' => $value]
        );
    }

    /**
     * Mutate status to is_deleted
     */
    protected function isDeleted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'deleted' ? 1 : 0,
        );
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'created_for_id');
    }

    public function createdBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'created_by_id');
    }

    public function updatedBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'updated_by_id');
    }

    public function injuryUpdates(): HasMany
    {
        return $this->hasMany(InjuryUpdate::class, 'injury_id', 'injury_id');
    }
}
