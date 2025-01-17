<?php

namespace App\Traits;

use App\Models\Scopes\StatusTrashScope;
use Illuminate\Database\Eloquent\Builder;

trait SoftDeletesStatus
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new StatusTrashScope);
    }

    public function delete(): bool
    {
        return $this->forceFill([
            'status' => 'deleted',
        ])->save();
    }

    public function scopeWithTrashed(Builder $builder): Builder
    {
        return $builder->withoutGlobalScopes([StatusTrashScope::class]);
    }

    public function scopeOnlyTrashed(Builder $builder): Builder
    {
        return $builder->withoutGlobalScopes([StatusTrashScope::class])
            ->where($this->getTable().'.status', 'deleted');
    }
}
