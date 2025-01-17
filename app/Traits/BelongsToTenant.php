<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\TenantUser;
use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    use Compoships;

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('userTenant', function (Builder $builder) {
            $builder->with('userTenant');
        });

    }

    public function userTenant(): HasOne
    {
        return $this->hasOne(TenantUser::class, ['user_id', 'box_id'], ['user_id', 'tenant_id'])->withTrashed()
            ->orderBy('deleted', 'asc');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
