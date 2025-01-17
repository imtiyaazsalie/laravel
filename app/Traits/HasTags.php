<?php

namespace App\Traits;

use App\Models\Tag;
use App\Models\Taggable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTags
{
    public function taggables(): MorphMany
    {
        return $this->morphMany(Taggable::class, 'taggables', 'morphable_model', 'morphable_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, Taggable::class, 'morphable_id', 'tag_id', null, 'id')
            ->using(Taggable::class)
            ->as('taggable')
            ->where('taggables.morphable_model', $this->getTable())
            ->whereNull('taggables.deleted_at');
    }
}
