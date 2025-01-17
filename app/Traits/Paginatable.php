<?php

namespace App\Traits;

trait Paginatable
{
    public function scopePaged($query)
    {
        return $query->when(request()->get('per_page') && request()->get('per_page') == '-1',
            fn ($query) => $query->get(),
            fn ($query) => $query->paginate(request()->get('per_page') ?: 25));
    }
}
