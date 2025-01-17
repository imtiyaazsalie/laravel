<?php

namespace App\Models;

use App\Enums\Affiliate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantAffiliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'affiliate_id' => Affiliate::class,
    ];

    /**
     * Tenant relation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function affiliate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->affiliate_id,
        );
    }

    public static function isAffiliate(Tenant|int $tenant, Affiliate $affiliate): bool
    {
        return self::query()
            ->where('tenant_id', $tenant)
            ->where('affiliate_id', $affiliate)
            ->exists();
    }

    public static function isNotAffiliate(Tenant|int $tenant, Affiliate $affiliate): bool
    {
        return ! self::isAffiliate($tenant, $affiliate);
    }
}
