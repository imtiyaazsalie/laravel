<?php

namespace App\Models;

use App\Enums\Affiliate;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Programme extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'integer',
        'affiliate_id' => Affiliate::class,
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public $timestamps = true;

    protected $table = 'programmes';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeAffiliate(Builder $query, Affiliate $affiliate): Builder
    {
        return $query->where('affiliate_id', $affiliate);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('box_id');
    }

    public static function globalAffiliateProgrammes(Affiliate $affiliate): Builder
    {
        return self::query()
            ->active()
            ->global()
            ->affiliate($affiliate);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'box_id', 'box_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'programme_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTO(User::class, 'created_by_id', 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'parent_id', 'id');
    }

    public function toggleStatus()
    {
        $this->update(['is_active' => ! $this->is_active]);
    }

    public function programmePackageVisibility(): HasMany
    {
        return $this->hasMany(ProgrammePackageVisibility::class, 'programme_id', 'id');
    }

    public function wods(): HasMany
    {
        return $this->hasMany(Wod::class, 'programme_id', 'id');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function affiliate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->affiliate_id,
            set: fn (mixed $value) => ['affiliate_id' => $value]
        );
    }

    public function scopeHasPackageVisibility(Builder $query, string|int|array $packageIds): Builder
    {
        $packageIds = (array) $packageIds;

        return $query->where(function ($query) use ($packageIds) {
            $query->whereDoesntHave('programmePackageVisibility')
                ->orWhereHas('programmePackageVisibility', function ($query) use ($packageIds) {
                    $query->whereIn('package_id', $packageIds);
                });
        });
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isNotActive(): bool
    {
        return ! $this->isActive();
    }

    public function isGlobal(): bool
    {
        return is_null($this->box_id);
    }

    public function hasGlobalParent(): bool
    {
        return ! is_null($this->parent_id);
    }
}
