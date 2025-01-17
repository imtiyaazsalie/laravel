<?php

namespace App\Traits;

use App\Models\Scopes\BooleanTrashScope;
use Illuminate\Database\Eloquent\Builder;

trait SoftDeletesBoolean
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new BooleanTrashScope);
    }

    public function delete(): bool
    {
        return $this->forceFill([
            'deleted' => true,
        ])->save();
    }

    public function scopeWithTrashed(Builder $builder): Builder
    {
        return $builder->withoutGlobalScopes([BooleanTrashScope::class]);
    }

    public function scopeOnlyTrashed(Builder $builder): Builder
    {
        return $builder->withoutGlobalScopes([BooleanTrashScope::class])
            ->where($this->getTable().'.deleted', true);
    }

    public function trashed(): bool
    {
        return (bool) $this->attributes['deleted'];
    }

    public function restore(): bool
    {
        return $this->update(['deleted' => false]);
    }
}
